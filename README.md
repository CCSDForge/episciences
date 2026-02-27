# LMCS Pipeline Integration for Episciences

## Project Overview

This project extends the workflow capabilities of Episciences to support the publication pipeline used by Logical Methods in Computer Science (LMCS).

Episciences is a diamond open-access overlay journal platform operated by CCSD (Centre pour la Communication Scientifique Directe). It integrates with arXiv, meaning that research papers are hosted on arXiv while Episciences manages peer review, editorial workflow, and publication metadata.

Currently, the default Episciences publication pipeline does not align with the LMCS editorial workflow. This mismatch causes inefficiencies, including paper queue resets, workflow confusion, and increased manual work. The goal of this project is to implement an LMCS-compatible pipeline within Episciences without affecting other journals.

---

## Problem Description

### Current Episciences Workflow

The default Episciences post-review workflow consists of a multi-step process in which:

1. Layout editors send changes to authors.
2. Authors upload revised versions through Episciences.
3. The process repeats until a final version is accepted.

This assumes authors upload the final versions themselves.

### LMCS Workflow

LMCS instead:

1. Requests a final version and arXiv password from the author.
2. Layout editors perform edits internally.
3. After author confirmation, LMCS uploads the final version to arXiv directly.

### Core Issue

Authors frequently ignore LMCS instructions and follow the default Episciences upload process. This results in:

* Papers being reset in the processing queue.
* Loss of waiting-time ordering.
* Increased manual tracking effort.
* Reduced clarity on deadlines and stalled tasks.

---

## Project Goals

### Minimum Viable Product (MVP)

* Implement the LMCS publication pipeline inside Episciences.
* Add a configurable admin setting allowing journals to switch between:

  * Default Episciences pipeline
  * LMCS pipeline

This ensures backward compatibility for other journals.

### Post-MVP Goals

* Extend the Episciences API with new endpoints useful for LMCS.
* Improve visibility of pending tasks and deadlines.
* Reduce reliance on external tools currently used by LMCS.

---

## Scrum Setup

### Roles

* Scrum Master: Raoul Rutgers
* Product Owner: LMCS, CCSD
* Development Team: Guus de Groot, Hamzeh Akkad, Jelle Prosperi, Kim Trinh, Willem Scholten

### Sprint Length

2 weeks

### Tools

* GitHub Issues
* GitHub Projects (Scrum board)
* Milestones (Sprints)
* Pull Requests and Code Reviews
* Docker for local development

---

## Technical Setup

### Requirements

* Linux-based system or virtual machine
* Docker and Docker Compose

### Setup Instructions

```bash
git clone git@github.com:CCSDForge/episciences.git
cd episciences
git checkout staging
make dev-setup
```

The platform should then be accessible locally via the configured port.

To access the urls, the domain names should be added to the local `etc/hosts` file.

```txt
127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org
```

---

## External Tools Currently Used by LMCS

Before this integration, LMCS currently relies on:

* **epirc**: A command-line tool that scrapes Episciences to track paper progress.
* **LMCSBot**: A Python web application that automates email reminders, requests final versions and passwords, and uploads papers to arXiv.

This project aims to integrate necessary functionality directly into Episciences to reduce dependency on these tools.

---

## Confidentiality

Reviewers may choose to remain anonymous. Reviewer identities must never be exposed to authors or unauthorized users. All implementations must preserve this confidentiality requirement.

---

## Contribution Workflow

1. Create a feature branch from `staging`.
2. Reference the related issue in commit messages.
3. Open a pull request.
4. Request peer review.
5. Merge after approval.

No external dependencies should be copied directly into this repository.

---

## Project Management

* Milestones represent sprints.
* Issues represent backlog items.
* GitHub Projects is used as the Scrum board.
* Labels are used to categorize tasks.

---

## License

This repository uses the MIT License unless otherwise specified. All dependencies must be license-compatible.
