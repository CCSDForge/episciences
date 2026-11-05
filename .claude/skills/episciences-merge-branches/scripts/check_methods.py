#!/usr/bin/env python3
"""Check method consistency after merging a branch into another one.

main, staging and the *-epi-manager branches do not share the same API: the
manager branches add traits (e.g. Episciences\\Trait\\UrlBuilder with
`final public static buildAdminPaperUrl()`), rename methods (adminPaperUrl()),
and main/staging keep their own versions (instance buildAdminPaperUrl()).
`php -l` cannot see these problems because traits and parent classes are not
loaded during linting; they only fail at runtime.

Usage (from the repository root):
    check_methods.py <before> <after> <source>
      <before>  target tip before the merge (SHA noted before merging)
      <after>   target tip to check (usually the target branch name)
      <source>  merged branch (e.g. staging)

Reports, for PHP/PHTML lines added between <before> and <after>:
  1. methods called but defined nowhere in <after>, while defined in <source>
     (code written against the source API);
  2. methods called statically (self::/static::/ProjectClass::) but defined
     only as non-static in <after> (calls on vendor/PHP classes are skipped);
  2b. methods whose signature differs between <source> and <after>: same file,
     or the source class method replaced by a project trait method on the
     target (e.g. buildAdminPaperUrl(int, ?string) vs (int, array));
  4. methods defined on the target before the merge, gone after it, but still
     called somewhere on the target (typically a conflict resolved by taking
     the whole source file);
and, for every class in <after> touched by the merge:
  3. methods redefined over an inherited one (from a parent class or from a
     trait used by a parent class): fatal if the inherited method is final or
     if static-ness differs ("Cannot override final method", "Cannot make
     static method non static");
  3b. methods redefined in a class that itself uses a project trait defining
     the same method. PHP accepts it (the class method silently replaces the
     trait one), but calls written for the trait version, e.g. self::x() from
     the trait or with the trait signature, now reach the class method.
Also printed as notes, not counted as issues:
  5. [unused] methods the merge adds to the target that nothing calls there
     (tests excluded): typically a helper written for the source mechanism
     while the target already does the same thing its own way, e.g. a link
     built from Episciences_Review::getUrl() on main, by UrlBuilder on the
     manager branches. A later fix of that helper on the source will have no
     effect on the target.
Calls are matched by method name only: string literals are ignored, but a
method of a vendor/PHP class sharing a name with a project method can still
produce a false positive (typically [removed]).
Read-only: it never modifies anything. Exit code 1 if an issue is reported
(notes do not change the exit code).
"""

import re
import subprocess
import sys

CODE_PATHS = ["application", "library", "scripts", "public/*.php"]
CALL_RE = re.compile(r"(\$?[A-Za-z_\\][A-Za-z0-9_\\]*)?\s*(->|::)\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(")
DEF_RE = re.compile(
    r"^\s*((?:(?:final|abstract|public|protected|private|static)\s+)*)function\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(",
    re.M,
)
# Class-level "use Trait;" is indented (spaces or tabs); namespace imports are not
TRAIT_USE_RE = re.compile(r"^[ \t]+use\s+([\\A-Za-z0-9_]+(?:\s*,\s*[\\A-Za-z0-9_]+)*)\s*[;{]", re.M)
TRAIT_DECL_RE = re.compile(r"^\s*trait\s+([A-Za-z_][A-Za-z0-9_]*)", re.M)
CLASS_RE = re.compile(
    r"^\s*(?:(?:abstract|final|readonly)\s+)*class\s+([A-Za-z_][A-Za-z0-9_]*)"
    r"(?:\s+extends\s+\\?([A-Za-z_\\][A-Za-z0-9_\\]*))?",
    re.M,
)
STRING_RE = re.compile(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"")


def git(*args):
    return subprocess.run(["git", *args], capture_output=True, text=True).stdout


def strip_strings(code):
    """Blank out string literals, except HTML attributes embedding PHP (<?= $this->url() ?>)."""
    return STRING_RE.sub(lambda m: m.group(0) if "<?" in m.group(0) else "''", code)


def calls(code):
    """(receiver, operator, method name) for every method call found in a line of code."""
    return [(m.group(1) or "", m.group(2), m.group(3)) for m in CALL_RE.finditer(strip_strings(code))]


def params_of(code, start):
    """Parameter list text of a single-line signature, normalized (None if multiline)."""
    depth, out = 1, []
    for ch in code[start:]:
        depth += (ch == "(") - (ch == ")")
        if depth == 0:
            text = re.sub(r"\s*=(?![=>])\s*", " = ","".join(out))
            return re.sub(r"\s+", " ", re.sub(r"\s*,\s*", ", ", text)).strip()
        out.append(ch)
    return None


def definitions(rev):
    """name -> list of (file, modifiers, params) for every method defined at rev."""
    out = git("grep", "-nE", r"function\s+&?\s*[A-Za-z_]", rev, "--", *CODE_PATHS)
    defs = {}
    for line in out.splitlines():
        _, path, _, code = line.split(":", 3)
        m = DEF_RE.match(code)
        if m:
            defs.setdefault(m.group(2), []).append((path, m.group(1).split(), params_of(code, m.end())))
    return defs


def added_lines(before, after):
    diff = git("diff", "--no-color", "-U0", f"{before}..{after}", "--", "*.php", "*.phtml",
               ":(exclude)tests/**", ":(exclude)vendor/**")
    file, line, rows = None, 0, []
    for raw in diff.splitlines():
        if raw.startswith("+++ b/"):
            file = raw[6:]
        elif raw.startswith("@@"):
            line = int(raw.split()[2].split(",")[0][1:])
        elif raw.startswith("+") and not raw.startswith("+++"):
            rows.append((file, line, raw[1:]))
            line += 1
    return rows


def project_traits(rev):
    """trait short name -> {method: modifiers} for traits defined in the repo."""
    traits = {}
    for path in git("grep", "-lE", r"^\s*trait\s", rev, "--", *CODE_PATHS).splitlines():
        path = path.split(":", 1)[1]
        src = git("show", f"{rev}:{path}")
        decl = TRAIT_DECL_RE.search(src)
        if decl:
            traits[decl.group(1)] = {m.group(2): m.group(1).split() for m in DEF_RE.finditer(src)}
    return traits


def project_classes(rev):
    """class short name -> path for classes defined in the repo."""
    out = git("grep", "-nE", r"^\s*((abstract|final|readonly)\s+)*class\s+[A-Za-z_]", rev, "--", *CODE_PATHS)
    classes = {}
    for line in out.splitlines():
        _, path, _, code = line.split(":", 3)
        m = CLASS_RE.match(code)
        if m:
            classes.setdefault(m.group(1), path)
    return classes


class Source:
    """Parsed PHP files of a revision, loaded on demand."""

    def __init__(self, rev, traits):
        self.rev, self.traits, self.cache = rev, traits, {}

    def parse(self, path):
        if path not in self.cache:
            src = git("show", f"{self.rev}:{path}")
            cls = CLASS_RE.search(src)
            own = {m.group(2): m.group(1).split() for m in DEF_RE.finditer(src)}
            used = [t.strip().split("\\")[-1] for use in TRAIT_USE_RE.findall(src) for t in use.split(",")]
            parent = cls.group(2).split("\\")[-1] if cls and cls.group(2) else None
            self.cache[path] = (own, used, parent)
        return self.cache[path]

    def inherited(self, parent, classes):
        """name -> (modifiers, origin) for methods inherited from parent and its own parents/traits."""
        methods, seen = {}, set()
        while parent and parent in classes and parent not in seen:
            seen.add(parent)
            own, used, grandparent = self.parse(classes[parent])
            for name, mods in own.items():
                methods.setdefault(name, (mods, parent))
            for trait in used:
                for name, mods in self.traits.get(trait, {}).items():
                    methods.setdefault(name, (mods, f"trait {trait} used by {parent}"))
            parent = grandparent
        return methods


def main():
    if len(sys.argv) != 4:
        print(__doc__)
        return 2
    before, after, source = sys.argv[1:]
    target_defs, source_defs = definitions(after), definitions(source)
    classes = project_classes(after)
    added = [row for row in added_lines(before, after) if not row[2].lstrip().startswith(("//", "/*", "*", "#"))]
    issues = []

    for path, lineno, code in added:
        for receiver, op, name in calls(code):
            where = f"{path}:{lineno}"
            if name not in target_defs:
                if name in source_defs:
                    files = sorted({p for p, _, _ in source_defs[name]})
                    issues.append(f"[missing] {where}: {name}() is defined on {source} "
                                  f"({', '.join(files)}) but nowhere on the target")
            elif op == "::" and (receiver in ("self", "static")
                                 or receiver.split("\\")[-1] in classes):
                if all("static" not in mods for _, mods, _ in target_defs[name]):
                    issues.append(f"[static-call] {where}: {name}() is called statically "
                                  f"but is not static on the target")

    before_defs = definitions(before)
    for name in sorted(set(before_defs) - set(target_defs)):
        callers = git("grep", "-nE", rf"(->|::)\s*{name}\s*\(", after, "--", *CODE_PATHS).splitlines()
        for c in callers[:5]:
            _, cpath, cline, _ = c.split(":", 3)
            issues.append(f"[removed] {cpath}:{cline}: calls {name}(), which the merge removed from the target "
                          f"(was in {before_defs[name][0][0]})")

    traits = project_traits(after)
    trait_files = {p for p in git("grep", "-lE", r"^\s*trait\s", after, "--", *CODE_PATHS).split()}
    trait_files = {p.split(":", 1)[1] for p in trait_files}
    reported = set()
    for path, lineno, code in added:
        for _, op, name in calls(code):
            if name in reported or name not in source_defs or name not in target_defs:
                continue
            for spath, _, sparams in source_defs[name]:
                same = [d for d in target_defs[name] if d[0] == spath]
                via_trait = [d for d in target_defs[name] if d[0] in trait_files and d[0] != spath]
                for tpath, tmods, tparams in (same or via_trait):
                    # self::x() / static::x() towards a static target method is written
                    # against the target API: not a divergence.
                    if op == "::" and "static" in tmods:
                        continue
                    if None not in (sparams, tparams) and sparams != tparams:
                        reported.add(name)
                        issues.append(f"[signature] {path}:{lineno}: {name}() differs: {source} {spath}"
                                      f"({sparams}) vs target {tpath}({tparams}) {' '.join(tmods)}")
    parsed = Source(after, traits)
    for path in sorted({p for p, _, _ in added}):
        own, used, parent = parsed.parse(path)
        # Redefinition of an inherited method (parent class or trait of a parent class)
        for name, (imods, origin) in parsed.inherited(parent, classes).items():
            if name not in own or "private" in imods or name == "__construct":
                continue
            if "final" in imods:
                issues.append(f"[FATAL] {path}: {name}() overrides a final method of {origin}")
            elif ("static" in imods) != ("static" in own[name]):
                issues.append(f"[FATAL] {path}: {name}() changes static-ness of {origin} "
                              f"({' '.join(imods)} vs {' '.join(own[name])})")
        # Redefinition of a method of a trait used by the class itself: accepted by PHP
        for trait in used:
            for name, tmods in traits.get(trait, {}).items():
                if name in own:
                    shadow = "final" in tmods or (("static" in tmods) != ("static" in own[name]))
                    issues.append(f"[{'shadow' if shadow else 'override'}] {path}: {name}() is defined "
                                  f"in the class and in trait {trait} ({' '.join(tmods)}): the class "
                                  f"method replaces the trait one, check calls written for the trait")

    # Methods added by the merge that nothing calls on the target (tests excluded)
    notes = []
    for path, lineno, code in added:
        m = DEF_RE.match(code)
        if not m or m.group(2).startswith("__") or m.group(2) in before_defs:
            continue
        name = m.group(2)
        refs = git("grep", "-nE", rf"((->|::)\s*{name}\s*\(|['\"]{name}['\"])", after, "--", *CODE_PATHS)
        if not refs.strip():
            notes.append(f"[unused] {path}:{lineno}: {name}() is added by the merge but never called on "
                         f"the target: check whether the target already does it another way")

    print(f"== Method check: {before[:12]}..{after} (source: {source}) ==")
    for issue in issues:
        print(issue)
    print(f"Issues: {len(issues)}")
    for note in notes:
        print(note)
    if notes:
        print(f"Notes: {len(notes)} (not counted as issues)")
    return 1 if issues else 0


if __name__ == "__main__":
    sys.exit(main())
