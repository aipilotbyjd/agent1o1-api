<?php

namespace Database\Seeders;

use App\Models\AgentSkill;
use App\Models\AgentSkillReference;
use App\Models\AgentSkillScript;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProductManagerSkillSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->error('No workspace found. Create a workspace first.');

            return;
        }

        $user = User::first();

        $this->command->info("Seeding Product Manager skill into workspace: {$workspace->name}");

        $skill = AgentSkill::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'product-manager'],
            [
                'created_by' => $user?->id,
                'name' => 'Product Manager',
                'description' => 'A comprehensive product management skill for writing PRDs, user stories, prioritization, roadmap planning, and stakeholder communication.',
                'instructions' => $this->instructions(),
                'is_shared' => true,
                'version' => 1,
            ]
        );

        $this->seedReferences($skill);
        $this->seedScripts($skill);

        $this->command->info("✅ Product Manager skill seeded successfully (ID: {$skill->id})");
    }

    private function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert Product Manager with deep experience across B2B SaaS, consumer apps, and platform products. You excel at translating business goals and user needs into clear, actionable product artifacts.

## Core Responsibilities

### Discovery & Research
- Conduct user interviews, analyze feedback, and synthesize insights into clear problem statements
- Use frameworks like Jobs-to-be-Done (JTBD), empathy maps, and user journey maps
- Define and track key metrics: activation rate, retention, NPS, ARPU, churn, and feature adoption
- Prioritize using data; supplement with qualitative signals when quantitative data is limited

### Product Requirements
- Write Product Requirements Documents (PRDs) that are specific, measurable, and implementation-ready
- Define clear acceptance criteria for every feature
- Break epics into user stories following the format: "As a [user type], I want [goal] so that [benefit]"
- Distinguish must-have (P0) from nice-to-have (P1/P2) features explicitly

### Prioritization
- Apply RICE scoring (Reach × Impact × Confidence ÷ Effort) to rank initiatives objectively
- Use ICE scoring for rapid prioritization: Impact × Confidence × Ease
- Apply MoSCoW method (Must, Should, Could, Won't) for release scoping
- Reference opportunity sizing and strategic alignment when justifying trade-offs

### Roadmapping
- Maintain a now/next/later roadmap that reflects strategic bets, not just a feature list
- Communicate roadmap with appropriate confidence levels (committed, likely, exploring)
- Align roadmap to company OKRs (Objectives and Key Results)
- Flag dependencies, risks, and assumptions explicitly

### Stakeholder Communication
- Write executive summaries that lead with impact, not activity
- Adapt communication style: concise for leadership, detailed for engineering
- Surface trade-offs clearly; never hide risks or uncertainties
- Send weekly/monthly product updates using a structured format: shipped, learnings, next

### Go-to-Market
- Write launch briefs covering target audience, value proposition, channels, and success metrics
- Define launch tiers (Tier 1 = major launch, Tier 2 = standard, Tier 3 = soft release)
- Coordinate with marketing, sales, and support on messaging and enablement materials

## Output Quality Standards
- Be specific and concrete — avoid vague terms like "improve UX" or "enhance performance"
- Quantify impact wherever possible ("reduce time-to-value from 3 days to 1 day")
- State assumptions clearly and call out what needs validation
- Every recommendation should answer: What? Why? How do we measure success?

## Communication Style
- Lead with the bottom line, then provide supporting context
- Use bullet points and tables for scannable structure
- Flag blockers, risks, and open questions explicitly
- Be opinionated but open to evidence that changes the recommendation
INSTRUCTIONS;
    }

    private function seedReferences(AgentSkill $skill): void
    {
        $skill->references()->delete();

        $references = [
            [
                'title' => 'PRD Template',
                'content' => <<<'REF'
# Product Requirements Document (PRD)

## Overview
**Feature Name:**
**PM Owner:**
**Eng Lead:**
**Target Release:**
**Status:** Draft | In Review | Approved

## Problem Statement
*What problem are we solving? For whom? Why now?*

## Goals & Success Metrics
| Goal | Metric | Baseline | Target |
|------|--------|----------|--------|
|      |        |          |        |

## Non-Goals
*Explicitly list what this feature will NOT do.*

## User Stories
- As a [user type], I want [goal] so that [benefit]

## Functional Requirements
### P0 — Must Have
- [ ] Requirement 1

### P1 — Should Have
- [ ] Requirement 2

### P2 — Nice to Have
- [ ] Requirement 3

## Acceptance Criteria
*Each requirement must have testable acceptance criteria.*

## UX / Design
*Link to mockups or describe key interaction patterns.*

## Technical Considerations
*Known constraints, dependencies, or architecture decisions.*

## Open Questions
| Question | Owner | Due Date |
|----------|-------|----------|
|          |       |          |

## Launch Plan
- Alpha/Beta: [date]
- GA: [date]
- Success review: [date]
REF,
                'sort_order' => 1,
            ],
            [
                'title' => 'RICE Prioritization Framework',
                'content' => <<<'REF'
# RICE Scoring Framework

RICE = (Reach × Impact × Confidence) ÷ Effort

## Definitions

**Reach** — How many users will this affect per quarter?
- Count the number of users or transactions, not a percentage

**Impact** — How much will this move the needle per user?
- 3 = Massive, 2 = High, 1 = Medium, 0.5 = Low, 0.25 = Minimal

**Confidence** — How confident are we in our estimates?
- 100% = High (strong data), 80% = Medium (some data), 50% = Low (mostly gut)

**Effort** — How many person-months will this take?
- Include design, engineering, QA, and PM time

## Scoring Template
| Initiative | Reach | Impact | Confidence | Effort | RICE Score |
|------------|-------|--------|------------|--------|------------|
| Feature A  |       |        |            |        |            |
| Feature B  |       |        |            |        |            |

## Tips
- Use the same time period (quarter) for all Reach estimates
- Confidence % dampens optimistic impact estimates
- Compare scores within a team; don't compare across very different teams
- Re-score quarterly as you gather more data
REF,
                'sort_order' => 2,
            ],
            [
                'title' => 'User Story & Acceptance Criteria Guide',
                'content' => <<<'REF'
# User Stories & Acceptance Criteria

## User Story Format
As a [specific user persona], I want [goal/action] so that [benefit/value].

**Good example:**
As a first-time user completing onboarding, I want to connect my calendar in one click so that my meetings sync automatically without manual setup.

**Bad example:**
As a user, I want a better onboarding so that it's easier.

## Acceptance Criteria (Gherkin Format)
Given [precondition], when [action], then [expected result].

Example:
Given I am on the onboarding screen and have not connected a calendar,
When I click "Connect Google Calendar" and authenticate,
Then my calendar events for the next 30 days appear in my dashboard within 5 seconds.

## Definition of Done Checklist
- [ ] All acceptance criteria pass
- [ ] Unit and integration tests written
- [ ] Reviewed by PM against original requirements
- [ ] Edge cases documented and handled
- [ ] Analytics events firing correctly
- [ ] Feature flag configured (if applicable)
- [ ] Docs / help center updated
REF,
                'sort_order' => 3,
            ],
            [
                'title' => 'OKR Writing Guide',
                'content' => <<<'REF'
# OKR Writing Guide (Objectives & Key Results)

## Structure
**Objective** — Qualitative, inspirational, time-bound direction.
**Key Results** — Quantitative metrics that measure progress toward the objective. 2-5 per objective.

## Objective Quality Checklist
- [ ] Ambitious but achievable
- [ ] Qualitative (no numbers in the objective itself)
- [ ] Answers: "What do we want to achieve?"
- [ ] Has a clear owner

## Key Result Quality Checklist
- [ ] Measurable and verifiable
- [ ] Outcome-focused (not output-focused)
- [ ] Has a baseline and target
- [ ] Graded 0–1 at end of period (0.7 = success, 1.0 = stretch achieved)

## Example
**Objective:** Become the go-to tool for product teams at high-growth startups.

**Key Results:**
- KR1: Increase weekly active teams from 200 to 500
- KR2: Achieve NPS of 50+ (baseline: 32)
- KR3: 40% of new signups come from referral (baseline: 18%)

## Anti-Patterns to Avoid
- "Ship feature X" — output, not outcome
- "Improve performance" — not measurable
- "100% of KRs hit" — not ambitious enough; 70% is the target
REF,
                'sort_order' => 4,
            ],
            [
                'title' => 'Go-to-Market Launch Brief Template',
                'content' => <<<'REF'
# Launch Brief Template

## Feature / Product: [Name]
**Launch Date:**
**Launch Tier:** Tier 1 (Major) | Tier 2 (Standard) | Tier 3 (Soft)
**PM Owner:**

## What We're Launching
*2-3 sentence description of the feature and what it does.*

## Why It Matters
*Business case and user value. Link to the PRD.*

## Target Audience
- Primary: [persona, segment, ICP]
- Secondary: [if applicable]

## Value Proposition
*One clear sentence: "[Product/Feature] helps [target] [achieve outcome] by [unique mechanism]."*

## Success Metrics
| Metric | Baseline | 30-day Target | 90-day Target |
|--------|----------|---------------|---------------|
|        |          |               |               |

## Launch Checklist
### Pre-Launch
- [ ] Feature complete and QA signed off
- [ ] Analytics instrumented and validated
- [ ] Help center article published
- [ ] Sales / CS enablement deck ready
- [ ] Email / in-app announcement drafted

### Launch Day
- [ ] Feature flag enabled for all users (or staged rollout %)
- [ ] Announcement sent
- [ ] Monitoring dashboard live

### Post-Launch
- [ ] 7-day metrics review
- [ ] Gather early user feedback
- [ ] Bugs triaged and addressed
- [ ] 30-day retrospective scheduled

## Risks & Mitigations
| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
|      |            |        |            |
REF,
                'sort_order' => 5,
            ],
        ];

        foreach ($references as $ref) {
            AgentSkillReference::query()->create([
                'skill_id' => $skill->id,
                'title' => $ref['title'],
                'content' => $ref['content'],
                'sort_order' => $ref['sort_order'],
            ]);
        }
    }

    private function seedScripts(AgentSkill $skill): void
    {
        $skill->scripts()->delete();

        AgentSkillScript::query()->create([
            'skill_id' => $skill->id,
            'name' => 'generate_rice_scores',
            'description' => 'Accepts a JSON array of initiatives with reach, impact, confidence, and effort values and returns them sorted by RICE score.',
            'language' => 'php',
            'code' => <<<'PHP'
<?php
// Input: JSON array via stdin
// Each item: { "name": string, "reach": number, "impact": number, "confidence": number, "effort": number }
// Output: JSON array sorted by RICE score descending

$input = trim(file_get_contents('php://stdin'));
$initiatives = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($initiatives)) {
    echo json_encode(['error' => 'Invalid JSON input']);
    exit(1);
}

$scored = array_map(function ($item) {
    $reach      = (float) ($item['reach']      ?? 0);
    $impact     = (float) ($item['impact']     ?? 0);
    $confidence = (float) ($item['confidence'] ?? 0) / 100;
    $effort     = (float) ($item['effort']     ?? 1);

    $rice = $effort > 0 ? round(($reach * $impact * $confidence) / $effort, 2) : 0;

    return array_merge($item, ['rice_score' => $rice]);
}, $initiatives);

usort($scored, fn($a, $b) => $b['rice_score'] <=> $a['rice_score']);

echo json_encode($scored, JSON_PRETTY_PRINT);
PHP,
            'is_enabled' => true,
        ]);

        AgentSkillScript::query()->create([
            'skill_id' => $skill->id,
            'name' => 'format_user_stories',
            'description' => 'Takes a list of raw feature ideas and formats them as properly structured user stories with acceptance criteria placeholders.',
            'language' => 'javascript',
            'code' => <<<'JS'
// Input: JSON array of feature ideas (strings) via stdin
// Output: Formatted user stories as markdown

const input = require('fs').readFileSync('/dev/stdin', 'utf8').trim();
let ideas;

try {
    ideas = JSON.parse(input);
} catch (e) {
    console.error('Invalid JSON input');
    process.exit(1);
}

if (!Array.isArray(ideas)) {
    console.error('Input must be a JSON array of strings');
    process.exit(1);
}

const stories = ideas.map((idea, i) => {
    return `## User Story ${i + 1}

**Feature Idea:** ${idea}

**User Story:**
As a [user persona], I want ${idea.toLowerCase()} so that [describe the benefit].

**Acceptance Criteria:**
- Given [precondition], when [action], then [expected result].
- Given [precondition], when [action], then [expected result].

**Priority:** P0 | P1 | P2
**Effort Estimate:** S | M | L | XL
**Notes:** _Add any edge cases or open questions here._

---`;
});

console.log('# User Stories\n\n' + stories.join('\n\n'));
JS,
            'is_enabled' => true,
        ]);
    }
}
