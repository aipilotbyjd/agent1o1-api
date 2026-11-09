---
title: Give an agent abilities
description: Let an agent use your apps, playbooks, skills and other agents.
sidebar_position: 2
---

# Give an agent abilities

An agent can only do what you give it. Abilities come in these kinds:

| Ability | What it lets the agent do |
|---|---|
| **App actions** | Use a connected app: send a Slack message, create a Jira issue, read a Google Sheet |
| **Playbooks** | Run one of your published playbooks, like "Generate the monthly report" |
| **Skills** | Follow a guide for a specific kind of task |
| **Knowledge** | Search your documents for answers |
| **Other agents** | Hand part of a job to a specialist agent |

## Add an ability

Open the agent, go to its abilities, and add what it needs. For app actions, choose the connection the agent should use.

## Lock the important settings

When you add an app action, you decide which settings the agent can choose, and which are fixed.

**Example:** for "Send Slack message", fix the channel to #support and let the agent write the message text. The agent can't post anywhere else, however it is asked.

Fix anything that could cause harm if it was wrong: accounts, folders, channels, recipients.

## Playbooks as abilities

A playbook's inputs become the things the agent needs to provide, and its outputs are what comes back. Name and describe your playbook inputs clearly so the agent uses them correctly.

## Only give what is needed

An agent with fewer abilities makes fewer mistakes. Start small, then add more as you gain trust.
