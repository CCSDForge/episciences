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
};
```

### Metric

```ts
type Metric = {
  total:    number;                  // cumul sur toute la période
  by_month: Record<string, number>;  // clé = "YYYY-MM", valeur = nb de visites humaines
};
```

---

## Règles métier

| Règle | Détail |
|-------|--------|
| **Bots exclus** | `ROBOT = 0` dans PAPER_STAT — filtrés par la commande `stats:process` |
| **Multi-versions** | Les stats de tous les DOCID d'un même article sont sommées sous un seul `paperid` |
| **`paperid`** | Identifiant stable à utiliser dans l'appli (le DOI est aussi unique) |
| **Mois** | Clés au format `YYYY-MM` (jamais `YYYY-MM-DD`) |
| **Tri** | Journaux triés alphabétiquement par rvcode ; articles triés par DOI |
| **Pas de stats** | Un article sans visites a `total: 0` et `by_month: {}` (toujours présent) |

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
          "downloads":  { "total": 340, "by_month": { "2024-03": 120, "2024-04": 220 } },
          "page_views": { "total": 800, "by_month": { "2024-03": 300, "2024-04": 500 } }
        },
        {
          "doi": "10.46298/epiga.2024.2",
          "paperid": 102,
          "publication_date": "2024-06-01",
          "downloads":  { "total": 0,  "by_month": {} },
          "page_views": { "total": 45, "by_month": { "2024-06": 45 } }
        }
      ]
    }
  }
}
```
