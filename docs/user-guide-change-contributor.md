# Change Contributor - User Guide

## Overview

The "Change Contributor" feature allows authorized users to transfer ownership of a paper from one contributor to another.

## Who Can Change the Contributor?

Only the following roles can change a paper's contributor:
- **Administrators**
- **Chief Editors**

## How to Change the Contributor

1. Navigate to the paper's administration page
2. In the **Contributor** panel, click the **Change contributor** button
3. A modal dialog opens:
   - Search for the new contributor by name or email
   - Select the user from the autocomplete results
   - Optionally check **"Add former contributor as co-author"** (checked by default)
4. Click **Confirm** to apply the change

## Add Former Contributor as Co-author Option

When this option is **checked**:
- The former contributor is added as a co-author of the paper
- They will continue to receive notifications about the paper
- They can still view the paper in their author space

When this option is **unchecked**:
- The former contributor loses all access to the paper
- They will no longer receive notifications about the paper

## Co-author Becoming Contributor

A co-author can be selected as the new contributor. When this happens:
- Their co-author role is automatically removed
- They become the main contributor (owner) of the paper

## Email Notifications

Two email notifications are sent when the contributor is changed:

### 1. New Contributor Notification
**Template**: `paper_new_contributor_notification`

Sent to the **new contributor** to inform them they are now responsible for the paper.

**Content includes**:
- Article title
- Link to manage the article in their author space

### 2. Former Contributor Notification
**Template**: `paper_former_contributor_notification`

Sent to the **former contributor** to inform them they are no longer the paper's owner.

**Content includes**:
- Article title
- Name of the new contributor
- Co-author status message (whether they were added as co-author or not)
- Link to view the article (only if added as co-author)

## Activity Log

The action is logged in the paper's history with the following details:
- **Action**: Contributor changed
- **Performed by**: User who made the change
- **Old contributor**: Name of the former contributor
- **New contributor**: Name of the new contributor
- **Co-author status**: Whether the former contributor was added as co-author

## Timeline Display

In the paper's timeline (history panel), the contributor change appears as:

```
Contributor changed    [Old Name] → [New Name]    [Date]
```

Clicking on the entry opens a modal with full details:
- Date and time
- User who performed the action
- Old contributor name
- New contributor name
- Co-author status (if applicable)

