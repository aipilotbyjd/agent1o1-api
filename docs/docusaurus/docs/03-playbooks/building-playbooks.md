---
title: Build a playbook
description: Use the editor to add steps, connect them, test, and let the AI builder do the heavy lifting.
sidebar_position: 2
---

# Build a playbook

## Start

On the **Playbooks** page, create a new playbook, or start from a [Blueprint](../07-pages-and-files/blueprints.md). Give it a clear name, such as "Lead processing".

## The editor

The editor is a canvas. Your steps are boxes, and lines between them show where information flows.

| To do this | Do this |
|---|---|
| Add a step | Open the step library, search, and drag a step onto the canvas |
| Connect steps | Drag from the output dot of one step to the input dot of another |
| Change settings | Select a step and fill in its settings panel |
| Move steps | Drag them. Use auto layout to tidy up |
| Delete a step | Select it and press Delete |
| Test | Choose **Run**, and select any step to see what it received and produced |

Your work is saved automatically as you go.

## Passing information between steps

Information flows along the lines you draw. In text fields you can also insert results from earlier steps, so a message can say "New order from [customer name]", where the customer name is filled in from an earlier step.

Information has a type: text, a list of text, a table, a file, true/false, or a number. The editor only lets you connect compatible types, and tells you when a connection doesn't fit. Use a step like **Array** or **Table** to convert between shapes.

## Inputs and outputs

- Add **Workflow Input** steps to ask for information when the playbook runs. Choose a name, label, type and default value.
- Add **Workflow Output** steps for what the playbook returns.

Inputs and outputs are what appear on the **Run** form, on a public [Page](../07-pages-and-files/pages.md), and when an agent uses your playbook as an ability.

## Let AI build it for you

Open the AI builder chat in the editor and describe what you want, for example "When I get a new Typeform response, summarize it and post to Slack". The builder adds and connects the steps.

- **Build** mode changes your canvas.
- **Ask** mode answers questions without changing anything.

Always review what it built and test before publishing.

## Test before you publish

Use **Run** to test with real data. If an earlier step is slow or costs credits, you can pin its sample output and test the later steps alone.

## Secrets

Never paste passwords or tokens into a step. Store them in the [Vault](../06-apps-and-keys/vault.md) and reference them.

## Next

- [Steps you can use](./steps.md)
- [Publish and versions](./publishing.md)
