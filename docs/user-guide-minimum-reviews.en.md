# Minimum Number of Reviews per Round - User Guide

*[English](user-guide-minimum-reviews.en.md) · [Français](user-guide-minimum-reviews.fr.md)*

## Overview

Your journal can require a **minimum number of reviews** before an editor can accept a paper, publish it or ask for a
revision. This minimum can now **change from one review round to the next**: for instance two reviews for the first
version, then only one for the revised versions.

A **review round** is a version of the paper: the first version is round 1, the first revised version round 2, and so
on. A **temporary version** (answer to a revision request without a new deposit in the repository) is a round too.

## Setting the Minimum

In **Journal settings**, **Editors settings** section (administrators, editors in chief and editorial secretaries
only):

| Setting | Role |
|---|---|
| **Minimum number of reviews** | minimum of the first versions |
| **Number of first versions subject to this minimum** | leave empty to apply the minimum to **every** version (usual behaviour) |
| **Minimum number of reviews on later versions** | minimum of the versions that follow; empty or 0: no blocking minimum |

### Examples

| Need | Minimum number | First versions | Later versions |
|---|---|---|---|
| Same minimum for every version (usual setting) | 2 | *(empty)* | *(empty)* |
| 2 reviews for the first version, then 1 | 2 | 1 | 1 |
| 2 reviews for the first two versions, then none | 2 | 2 | *(empty)* |

If you leave the two new settings untouched, nothing changes for your journal.

## What the Minimum Holds Back

As long as the minimum of the version is not reached:

- **editors** and **guest editors** can neither accept, publish nor ask for a revision: these choices are greyed out in
  the **Change article status** menu, with the minimum of the version in a tooltip;
- the request is also refused when it is sent otherwise than through the menu;
- **editors in chief**, **editorial secretaries** and **administrators** are not held back.

The minimum is also used for:

- the **reviewed pending editorial decision** status of the paper (reached when the minimum of the version is
  reached);
- the **"not enough reviewers"** reminder sent to the editors: it follows the minimum of each version.

## Frequently Asked Questions

**I typed 0 in "Minimum number of reviews on later versions" and the field is empty once saved: is that normal?**
Yes: empty and 0 have the same effect. No minimum holds the editors back, and the paper gets the "reviewed" status
once every reviewer who accepted has submitted their report.

**Does a temporary version accepted, then replaced by the final version, count as two rounds?**
The temporary version is a round; the final version submitted afterwards is another one, but it does not go through
review again.
