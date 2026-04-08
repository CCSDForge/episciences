# Format du fichier `data/kpi_downloads.json`

Généré par `scripts/GenerateDownloadKpiCommand.php` via `make stats-download-kpi`.

## Régénération

```bash
make stats-download-kpi pretty=1             # tous les journaux, JSON indenté
make stats-download-kpi rvcode=epiga         # un seul journal
make stats-download-kpi output=/srv/kpi.json # chemin personnalisé
make stats-download-kpi dry-run=1            # résumé console, sans écriture
```

---

## Schéma TypeScript

### Racine

```ts
type KpiFile = {
  generated_at:   string;                   // ISO 8601, ex: "2026-04-06T14:00:00+00:00"
  total_papers:   number;                   // nb total d'articles publiés avec DOI
  total_journals: number;                   // nb de revues dans le fichier
  journals:       Record<string, Journal>;  // clé = rvcode (ex: "epiga")
};
```

### Journal

```ts
type Journal = {
  rvid:         number;   // ID numérique de la revue
  name:         string;   // Nom lisible de la revue
  papers_count: number;   // nb d'articles dans cette revue
  papers:       Paper[];
};
```

### Paper

```ts
type Paper = {
  doi:              string;        // DOI canonique, ex: "10.46298/epiga.2024.123"
  paperid:          number;        // identifiant logique (toutes versions confondues)
  publication_date: string | null; // "YYYY-MM-DD" ou null
  downloads:        Metric;        // téléchargements PDF  (CONSULT = 'file')
  page_views:       Metric;        // vues de la page abstract (CONSULT = 'notice')
  geo:              GeoMap;        // répartition géographique des consultations
};
```

### Metric

```ts
type Metric = {
  total:   number;                  // cumul sur toute la période
  by_year: Record<string, number>;  // clé = "YYYY", valeur = nb de visites humaines
};
```

### GeoMap

```ts
// Clé = code ISO 3166-1 alpha-2 (ex: "FR", "US") ou "" si pays inconnu.
// L'objet est {} (pas []) quand aucune visite géolocalisée.
type GeoMap = Record<string, GeoEntry>;

type GeoEntry = {
  continent:  string; // code continent GeoIP (ex: "EU", "NA") ou "" si inconnu
  downloads:  number; // nb de téléchargements PDF depuis ce pays
  page_views: number; // nb de vues de la page abstract depuis ce pays
};
```

---

## Règles métier

| Règle | Détail |
|-------|--------|
| **Bots exclus** | `ROBOT = 0` dans PAPER_STAT — filtrés par `stats:process` |
| **Multi-versions** | Stats de tous les DOCID d'un même PAPERID sommées ; `paperid` est l'identifiant stable |
| **Granularité temporelle** | Annuelle (`by_year` = `"YYYY"`) pour limiter la taille du fichier |
| **Géographie** | Par pays (ISO alpha-2) avec continent ; pays inconnu = clé `""` |
| **Tri journaux** | Alphabétique par rvcode |
| **Tri pays** | Alphabétique dans `geo` (clé vide `""` en dernier) |
| **Pas de stats** | `total: 0`, `by_year: {}`, `geo: {}` — toujours présents |

---

## Exemple minimal

```json
{
  "generated_at": "2026-04-06T14:00:00+00:00",
  "total_papers": 2,
  "total_journals": 1,
  "journals": {
    "epiga": {
      "rvid": 5,
      "name": "Épijournal de Géométrie Algébrique",
      "papers_count": 2,
      "papers": [
        {
          "doi": "10.46298/epiga.2024.1",
          "paperid": 101,
          "publication_date": "2024-03-10",
          "downloads":  { "total": 340, "by_year": { "2024": 120, "2025": 220 } },
          "page_views": { "total": 800, "by_year": { "2024": 300, "2025": 500 } },
          "geo": {
            "DE": { "continent": "EU", "downloads": 40,  "page_views": 100 },
            "FR": { "continent": "EU", "downloads": 200, "page_views": 500 },
            "US": { "continent": "NA", "downloads": 100, "page_views": 200 },
            "":   { "continent": "",   "downloads": 0,   "page_views": 10  }
          }
        },
        {
          "doi": "10.46298/epiga.2024.2",
          "paperid": 102,
          "publication_date": "2024-06-01",
          "downloads":  { "total": 0, "by_year": {} },
          "page_views": { "total": 45, "by_year": { "2024": 45 } },
          "geo": {}
        }
      ]
    }
  }
}
```

---

## Patterns Next.js utiles

```ts
// Itérer sur tous les articles d'une revue
kpi.journals["epiga"].papers.forEach(paper => { ... });

// Top 5 pays par téléchargements pour un article
const top5 = Object.entries(paper.geo)
  .filter(([country]) => country !== "")
  .sort(([, a], [, b]) => b.downloads - a.downloads)
  .slice(0, 5);

// Évolution annuelle (pour un graphe)
const years = Object.keys(paper.downloads.by_year).sort();
const values = years.map(y => paper.downloads.by_year[y]);

// Total mondial téléchargements d'une revue
const total = journal.papers.reduce((s, p) => s + p.downloads.total, 0);
```
