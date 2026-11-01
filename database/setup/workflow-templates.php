<?php

/**
 * Global starter workflows offered in every workspace. Each one is a real
 * working process built from the node library: a run form (Input steps),
 * AI steps for the judgement work, and the app steps that act on the result.
 *
 * Connections are never stored here. A user picks their own account for each
 * app step after using the template, and steps that need a connection say so
 * in the editor. Values come from earlier steps with `{{ nodes.key.field }}`;
 * an Input step's answer is `{{ nodes.key.value }}` and an AI step's reply is
 * `{{ nodes.key.text }}`.
 *
 * Positions are laid out from the edges, so a template only lists its steps
 * and how they connect.
 */

/** A run-form field. */
$input = fn (string $key, string $label, string $type = 'string', bool $required = true, ?string $help = null): array => [
    'key' => $key,
    'type' => 'workflow_input',
    'config' => array_filter([
        'name' => $key,
        'label' => $label,
        'input_type' => $type,
        'required' => $required,
        'description' => $help,
    ], fn ($value): bool => $value !== null),
];

/** An AI step. */
$ai = fn (string $key, string $instructions, string $prompt): array => [
    'key' => $key,
    'type' => 'ask_ai',
    'config' => ['instructions' => $instructions, 'prompt' => $prompt],
];

/** Any other step. */
$step = fn (string $key, string $type, array $config = []): array => ['key' => $key, 'type' => $type, 'config' => $config];

/** A returned value. */
$output = fn (string $key, string $name, string $value): array => $step($key, 'workflow_output', ['name' => $name, 'value' => $value]);

/** An answer from a run-form field. */
$v = fn (string $key): string => '{{ nodes.'.$key.'.value }}';

/** The reply of an AI step. */
$t = fn (string $key): string => '{{ nodes.'.$key.'.text }}';

/** Edges: [from, to] or [from, to, branch]. */
$edges = fn (array ...$pairs): array => array_map(
    fn (array $pair): array => ['from' => $pair[0], 'to' => $pair[1], 'condition' => $pair[2] ?? null],
    $pairs,
);

/** A route for the Router step. */
$route = fn (string $path, string $operator, string $value, string $branch): array => ['path' => $path, 'operator' => $operator, 'value' => $value, 'result' => $branch];

/**
 * Lays the steps out left to right by how far each is from the start, and
 * returns the graph.
 *
 * @param  array<int, array<string, mixed>>  $nodes
 * @param  array<int, array<string, mixed>>  $edges
 * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
 */
$graph = function (array $nodes, array $edges): array {
    $depth = array_fill_keys(array_column($nodes, 'key'), 0);

    for ($pass = 0; $pass < count($nodes); $pass++) {
        foreach ($edges as $edge) {
            $depth[$edge['to']] = max($depth[$edge['to']], $depth[$edge['from']] + 1);
        }
    }

    $rows = [];

    foreach ($nodes as &$node) {
        $column = $depth[$node['key']];
        $row = $rows[$column] = ($rows[$column] ?? -1) + 1;
        $node['position'] = ['x' => $column * 340, 'y' => $row * 170];
    }

    return ['nodes' => $nodes, 'edges' => $edges];
};

$ONLY_FACTS = 'Use only the information provided. Never invent facts, figures or contact details; say what is missing instead.';

$templates = [];

/* ------------------------------------------------------------------ Sales */

$templates['lead-intake-and-scoring'] = [
    'name' => 'Lead intake, scoring and routing',
    'description' => 'Saves a new lead to HubSpot, has AI rate it hot, warm or cold against your ideal customer, alerts Slack for hot leads and drafts a nurture email for the rest.',
    'category' => 'Sales & Outreach',
    'icon' => 'target',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $input('first_name', 'First name'),
        $input('last_name', 'Last name'),
        $input('email', 'Email'),
        $input('company', 'Company', required: false),
        $input('message', 'What they asked for', 'text', false),
        $input('ideal_customer', 'Ideal customer profile', 'text', true, 'Who you sell to: company size, industries, roles and budget.'),
        $step('save_contact', 'hubspot_create_contact', ['email' => $v('email'), 'first_name' => $v('first_name'), 'last_name' => $v('last_name')]),
        $step('score', 'ai_classify', [
            'text' => 'Lead: '.$v('first_name').' '.$v('last_name').', company '.$v('company').".\nMessage: ".$v('message'),
            'categories' => ['hot', 'warm', 'cold'],
            'guidance' => "Ideal customer profile:\n".$v('ideal_customer')."\n\nhot: matches the profile and shows buying intent (asks for a demo, pricing or a call). warm: matches the profile but shows no clear intent yet. cold: does not match the profile or gives too little to tell.",
        ]),
        $step('alert_team', 'slack_post_message', ['channel' => '#sales', 'text' => 'Hot lead: '.$v('first_name').' '.$v('last_name').' ('.$v('company').') - '.$v('email')."\n".'{{ nodes.score.reason }}'."\n".$v('message')]),
        $ai('nurture_email', "You write short, warm, specific follow-up emails for sales. Under 110 words, one clear next step, no buzzwords. {$ONLY_FACTS}", 'Write a follow-up to '.$v('first_name').' at '.$v('company').' who wrote: '.$v('message')),
        $step('draft_nurture', 'gmail_create_draft', ['to' => $v('email'), 'subject' => 'Following up on your enquiry', 'body' => $t('nurture_email')]),
        $output('result', 'lead_rating', '{{ nodes.score.label }}'),
    ], $edges(
        ['first_name', 'save_contact'], ['last_name', 'save_contact'], ['email', 'save_contact'], ['company', 'save_contact'], ['message', 'save_contact'], ['ideal_customer', 'save_contact'],
        ['save_contact', 'score'],
        ['score', 'alert_team', 'hot'], ['score', 'nurture_email', 'warm'], ['score', 'nurture_email', 'cold'],
        ['nurture_email', 'draft_nurture'], ['alert_team', 'result'], ['draft_nurture', 'result'],
    )),
];

$templates['sales-call-prep-brief'] = [
    'name' => 'Sales call prep brief',
    'description' => 'Give it a prospect and their company and it emails you a one-page brief with likely pains, discovery questions and objections.',
    'category' => 'Sales & Outreach',
    'icon' => 'briefcase',
    'color' => 'purple',
    'graph' => $graph([
        $input('prospect', 'Prospect name and role'),
        $input('company', 'Company'),
        $input('notes', 'What you know so far', 'text', false, 'Paste website copy, LinkedIn text or CRM notes.'),
        $input('your_email', 'Send the brief to'),
        $ai('brief', "You prepare sales reps for calls. Write a one-page brief: who they are, what the company does, likely priorities (labelled as assumptions), five discovery questions, likely objections with honest responses, and a goal for the call. {$ONLY_FACTS}", 'Prospect: '.$v('prospect')."\nCompany: ".$v('company')."\nNotes: ".$v('notes')),
        $step('email_brief', 'gmail_send_email', ['to' => $v('your_email'), 'subject' => 'Call prep: '.$v('prospect').' at '.$v('company'), 'body' => $t('brief')]),
        $output('result', 'brief', $t('brief')),
    ], $edges(['prospect', 'brief'], ['company', 'brief'], ['notes', 'brief'], ['your_email', 'brief'], ['brief', 'email_brief'], ['email_brief', 'result'])),
];

$templates['cold-email-sequence-drafts'] = [
    'name' => 'Cold email and follow-ups',
    'description' => 'Writes a personalised first email and two follow-ups from a trigger event, and saves the first as a Gmail draft ready to send.',
    'category' => 'Sales & Outreach',
    'icon' => 'mail',
    'color' => 'green',
    'graph' => $graph([
        $input('prospect_email', 'Prospect email'),
        $input('prospect_name', 'Prospect name and role'),
        $input('trigger', 'Why now', 'text', true, 'A specific trigger: funding, a hire, a post, a product launch.'),
        $input('offer', 'What you offer', 'text'),
        $ai('sequence', "You write cold outreach. Write a first email under 100 words (relevant opener tied to the trigger, one sentence on the problem, one on the outcome, a low-pressure question), then two follow-ups three and seven days later that each add a new angle. Label them Email 1, Follow-up 1, Follow-up 2 and give a subject line for Email 1. No buzzwords or false urgency. {$ONLY_FACTS}", 'Prospect: '.$v('prospect_name')."\nWhy now: ".$v('trigger')."\nOffer: ".$v('offer')),
        $step('save_draft', 'gmail_create_draft', ['to' => $v('prospect_email'), 'subject' => 'Quick question', 'body' => $t('sequence')]),
        $output('result', 'sequence', $t('sequence')),
    ], $edges(['prospect_email', 'sequence'], ['prospect_name', 'sequence'], ['trigger', 'sequence'], ['offer', 'sequence'], ['sequence', 'save_draft'], ['save_draft', 'result'])),
];

$templates['meeting-follow-up-and-crm-note'] = [
    'name' => 'Meeting follow-up email and CRM note',
    'description' => 'Turns call notes into a recap email draft to the customer and a task on the HubSpot contact so next steps are never lost.',
    'category' => 'Sales & Outreach',
    'icon' => 'clipboard-list',
    'color' => 'orange',
    'graph' => $graph([
        $input('customer_email', 'Customer email'),
        $input('hubspot_contact_id', 'HubSpot contact ID'),
        $input('notes', 'Call notes or transcript', 'text'),
        $ai('recap', "You write concise customer follow-up emails after sales calls: thank them, recap what was agreed, list next steps with owners and dates, and ask for confirmation. Under 180 words. {$ONLY_FACTS}", $v('notes')),
        $ai('next_step', 'Reply with a single sentence naming the most important next step and who owns it.', $v('notes')),
        $step('draft_email', 'gmail_create_draft', ['to' => $v('customer_email'), 'subject' => 'Recap and next steps', 'body' => $t('recap')]),
        $step('crm_task', 'hubspot_create_task', ['object' => 'contacts', 'record_id' => $v('hubspot_contact_id'), 'subject' => 'Follow up after call', 'body' => $t('next_step')]),
    ], $edges(['customer_email', 'recap'], ['hubspot_contact_id', 'recap'], ['notes', 'recap'], ['notes', 'next_step'], ['recap', 'draft_email'], ['next_step', 'crm_task'])),
];

/* -------------------------------------------------------------- Marketing */

$templates['blog-to-social-posts'] = [
    'name' => 'Blog post to social posts',
    'description' => 'Repurposes an article into a LinkedIn post and an X thread and sends both to Slack for review before anything is published.',
    'category' => 'Marketing & Content',
    'icon' => 'megaphone',
    'color' => 'purple',
    'is_featured' => true,
    'graph' => $graph([
        $input('article', 'Article text or link summary', 'text'),
        $input('voice', 'Brand voice', 'string', false, 'e.g. friendly, direct, no jargon'),
        $ai('linkedin', "You write LinkedIn posts: a strong first line, 120 to 200 words, short paragraphs, a question at the end. {$ONLY_FACTS}", 'Voice: '.$v('voice')."\nArticle:\n".$v('article')),
        $ai('thread', "You write X threads: five posts, each under 270 characters, the first a hook, the last a call to action. {$ONLY_FACTS}", 'Voice: '.$v('voice')."\nArticle:\n".$v('article')),
        $step('review', 'slack_post_message', ['channel' => '#marketing', 'text' => "Social drafts ready for review\n\n*LinkedIn*\n".$t('linkedin')."\n\n*X thread*\n".$t('thread')]),
        $output('result', 'linkedin_post', $t('linkedin')),
    ], $edges(['article', 'linkedin'], ['voice', 'linkedin'], ['article', 'thread'], ['voice', 'thread'], ['linkedin', 'review'], ['thread', 'review'], ['review', 'result'])),
];

$templates['seo-content-brief-to-doc'] = [
    'name' => 'SEO content brief to Google Doc',
    'description' => 'Builds a content brief for a keyword (intent, outline, questions to answer, internal links) and saves it as a Google Doc.',
    'category' => 'Marketing & Content',
    'icon' => 'search',
    'color' => 'teal',
    'graph' => $graph([
        $input('keyword', 'Target keyword'),
        $input('audience', 'Target reader'),
        $input('product', 'What you want them to do next', 'string', false),
        $ai('brief', "You are an SEO content strategist. Write a brief with: search intent, working title and meta description (under 155 characters), a detailed H2/H3 outline, questions the article must answer, entities and terms to include, suggested internal link topics, and the call to action. Do not invent search volumes or statistics. {$ONLY_FACTS}", 'Keyword: '.$v('keyword')."\nReader: ".$v('audience')."\nNext action: ".$v('product')),
        $step('save_doc', 'google_docs_create_document', ['title' => 'Content brief: '.$v('keyword'), 'content' => $t('brief')]),
        $output('result', 'brief', $t('brief')),
    ], $edges(['keyword', 'brief'], ['audience', 'brief'], ['product', 'brief'], ['brief', 'save_doc'], ['save_doc', 'result'])),
];

$templates['weekly-search-performance-report'] = [
    'name' => 'Weekly search performance report',
    'description' => 'Every Monday pulls last week’s Search Console queries, has AI explain what changed and what to do about it, and posts it to Slack.',
    'category' => 'Marketing & Content',
    'icon' => 'chart-line',
    'color' => 'blue',
    'graph' => $graph([
        $input('site_url', 'Search Console property', 'string', true, 'e.g. https://example.com/ or sc-domain:example.com'),
        $step('monday', 'schedule_trigger', ['cron' => '0 8 * * 1']),
        $step('start', 'date_time', ['operation' => 'subtract', 'value' => '{{ nodes.today.result }}', 'amount' => 7, 'unit' => 'days', 'format' => 'Y-m-d']),
        $step('today', 'date_time', ['operation' => 'now', 'format' => 'Y-m-d']),
        $step('queries', 'google_search_console_query_search_analytics', ['site_url' => $v('site_url'), 'start_date' => '{{ nodes.start.result }}', 'end_date' => '{{ nodes.today.result }}', 'dimensions' => 'query', 'row_limit' => 50]),
        $ai('analysis', "You are an SEO analyst. From the query rows (clicks, impressions, CTR, position) write: a headline, the five biggest opportunities (high impressions with low CTR or position 8 to 20), anything that dropped, and three specific actions. Show numbers from the data only. {$ONLY_FACTS}", "Last 7 days of search queries:\n{{ nodes.queries.rows }}"),
        $step('post', 'slack_post_message', ['channel' => '#marketing', 'text' => "*Weekly search report*\n".$t('analysis')]),
    ], $edges(['monday', 'today'], ['today', 'start'], ['site_url', 'queries'], ['start', 'queries'], ['queries', 'analysis'], ['analysis', 'post'])),
];

$templates['ad-copy-variants-to-sheet'] = [
    'name' => 'Ad copy variants to Google Sheets',
    'description' => 'Writes search-ad headline and description variants for an offer and logs them in a sheet so you can track which ones you test.',
    'category' => 'Marketing & Content',
    'icon' => 'megaphone',
    'color' => 'red',
    'graph' => $graph([
        $input('product', 'Product or offer', 'text'),
        $input('audience', 'Audience'),
        $input('spreadsheet_id', 'Google Sheet ID'),
        $ai('copy', "You write Google search ads. Write 5 headlines (30 characters max each) and 3 descriptions (90 characters max each), each testing a different angle: pain, benefit, proof. Count characters. No unverifiable superlatives. {$ONLY_FACTS}", 'Product: '.$v('product')."\nAudience: ".$v('audience')),
        $step('log', 'google_sheets_append_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => 'Ad copy!A:C', 'values' => ['{{ nodes.product.value }}', '{{ nodes.audience.value }}', '{{ nodes.copy.text }}']]),
    ], $edges(['product', 'copy'], ['audience', 'copy'], ['spreadsheet_id', 'copy'], ['copy', 'log'])),
];

/* ------------------------------------------------------ Customer support */

$templates['support-email-triage-and-reply'] = [
    'name' => 'Support triage with approved replies',
    'description' => 'Classifies an incoming request by urgency, alerts Slack for urgent ones, and drafts a reply that a person approves before it is sent.',
    'category' => 'Support & Success',
    'icon' => 'headphones',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $input('customer_email', 'Customer email'),
        $input('subject', 'Subject'),
        $input('message', 'Message', 'text'),
        $input('help_content', 'Help-center content and policies', 'text', false, 'Paste the articles or policies the reply may rely on.'),
        $step('urgency', 'ai_classify', [
            'text' => $v('subject')."\n".$v('message'),
            'categories' => ['urgent', 'normal'],
            'guidance' => 'urgent: an outage, data loss, a security concern or a payment failure. normal: everything else.',
        ]),
        $step('alert', 'slack_post_message', ['channel' => '#support-urgent', 'text' => 'Urgent request from '.$v('customer_email').': '.$v('subject')."\n".'{{ nodes.urgency.reason }}'."\n".$v('message')]),
        $ai('draft', "You draft support replies for a human to approve. Acknowledge the problem in one sentence, give numbered steps using only the supplied help content (if it does not cover it, say what you are checking), say what happens next. Never promise refunds, dates or features the policy does not state. {$ONLY_FACTS}", "Help content:\n".$v('help_content')."\n\nCustomer message:\n".$v('subject')."\n".$v('message')),
        $step('approve', 'human_approval'),
        $step('send', 'gmail_send_email', ['to' => $v('customer_email'), 'subject' => 'Re: '.$v('subject'), 'body' => $t('draft')]),
    ], $edges(
        ['customer_email', 'urgency'], ['subject', 'urgency'], ['message', 'urgency'], ['help_content', 'urgency'],
        ['urgency', 'alert', 'urgent'], ['urgency', 'draft', 'normal'], ['alert', 'draft'],
        ['draft', 'approve'], ['approve', 'send'],
    )),
];

$templates['customer-feedback-theme-report'] = [
    'name' => 'Customer feedback theme report',
    'description' => 'Groups a batch of feedback into themes with counts, quotes and top fixes, then posts the summary to Slack and logs it in Notion.',
    'category' => 'Support & Success',
    'icon' => 'headphones',
    'color' => 'green',
    'graph' => $graph([
        $input('feedback', 'Feedback, tickets or reviews', 'text'),
        $input('period', 'Period covered', 'string', false),
        $input('notion_parent_page', 'Notion page to save under'),
        $ai('themes', "You summarise customer feedback for a product team. Group it into themes (bugs, feature requests, usability, billing, other), give the number of mentions, a one-sentence summary and one or two quotes per theme with personal details removed, highlight anything urgent, and end with the three changes that address the most feedback. Under one page. {$ONLY_FACTS}", 'Period: '.$v('period')."\n\n".$v('feedback')),
        $step('post', 'slack_post_message', ['channel' => '#product', 'text' => '*Feedback report* '.$v('period')."\n".$t('themes')]),
        $step('save', 'notion_create_page', ['parent_page' => $v('notion_parent_page'), 'title' => 'Feedback report '.$v('period'), 'content' => $t('themes')]),
    ], $edges(['feedback', 'themes'], ['period', 'themes'], ['notion_parent_page', 'themes'], ['themes', 'post'], ['themes', 'save'])),
];

$templates['ticket-to-github-issue'] = [
    'name' => 'Bug report to GitHub issue',
    'description' => 'Turns a customer’s bug report into a clean GitHub issue with steps to reproduce, expected and actual behaviour, and a severity label.',
    'category' => 'Support & Success',
    'icon' => 'code',
    'color' => 'orange',
    'graph' => $graph([
        $input('repo', 'Repository (owner/name)'),
        $input('report', 'Customer bug report', 'text'),
        $step('bug', 'ai_extract', [
            'text' => $v('report'),
            'fields' => [
                'title' => 'a GitHub issue title under 80 characters that states the problem',
                'steps' => 'the steps to reproduce, as a numbered list',
                'expected' => 'what the customer expected to happen',
                'actual' => 'what actually happened, quoting any error message exactly',
                'environment' => 'browser, device, operating system or version if mentioned',
            ],
        ]),
        $step('severity', 'ai_classify', [
            'text' => $v('report'),
            'categories' => ['low', 'medium', 'high'],
            'guidance' => 'high: data loss, a security problem, a payment problem or something that blocks many people. medium: a feature is broken but there is a workaround. low: cosmetic or rare.',
        ]),
        $step('create', 'github_create_issue', [
            'repo' => $v('repo'),
            'title' => '{{ nodes.bug.title }}',
            'body' => "## Steps to reproduce\n{{ nodes.bug.steps }}\n\n## Expected\n{{ nodes.bug.expected }}\n\n## Actual\n{{ nodes.bug.actual }}\n\n## Environment\n{{ nodes.bug.environment }}\n\nSeverity: {{ nodes.severity.label }} - {{ nodes.severity.reason }}",
            'labels' => ['bug', 'from-support'],
        ]),
    ], $edges(['repo', 'bug'], ['report', 'bug'], ['report', 'severity'], ['bug', 'create'], ['severity', 'create'])),
];

$templates['negative-review-alert-and-reply'] = [
    'name' => 'Review response drafts with low-rating alerts',
    'description' => 'Drafts an on-brand reply to a customer review and alerts the team in Slack when the rating is low so a person handles it first.',
    'category' => 'Support & Success',
    'icon' => 'star',
    'color' => 'purple',
    'graph' => $graph([
        $input('review', 'Review text', 'text'),
        $input('rating', 'Star rating (1 to 5)', 'number'),
        $input('reviewer', 'Reviewer first name', 'string', false),
        $ai('reply', "You reply to customer reviews in under 80 words. Positive: thank them for something specific. Negative: apologise for the specific problem, say what you will do and give an offline way to reach support. Never defensive, never offer discounts or refunds, never reveal personal data. {$ONLY_FACTS}", 'Rating: '.$v('rating')."\nReviewer: ".$v('reviewer')."\nReview: ".$v('review')),
        $step('route', 'router', ['conditions' => [$route('nodes.rating.value', 'less_or_equal', '2', 'low')]]),
        $step('alert', 'slack_post_message', ['channel' => '#support', 'text' => 'Low review ('.$v('rating')."/5) needs a person.\nReview: ".$v('review')."\nDraft reply: ".$t('reply')]),
        $step('post_draft', 'slack_post_message', ['channel' => '#reviews', 'text' => 'Draft reply for review ('.$v('rating').'/5): '.$t('reply')]),
    ], $edges(['review', 'reply'], ['rating', 'reply'], ['reviewer', 'reply'], ['reply', 'route'], ['route', 'alert', 'low'], ['route', 'post_draft', 'default'])),
];

/* -------------------------------------------------------------------- HR */

$templates['resume-screening-with-shortlist'] = [
    'name' => 'Resume screening and shortlist',
    'description' => 'Scores a resume against the job’s requirements with the evidence for and against, and drafts an interview invitation only for strong matches.',
    'category' => 'HR & Recruiting',
    'icon' => 'users',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $input('job_description', 'Job description and must-haves', 'text'),
        $input('resume', 'Resume text', 'text'),
        $input('candidate_email', 'Candidate email'),
        $step('screen', 'ai_score', [
            'text' => $v('resume'),
            'criteria' => "Score this resume against the role below. Judge only on skills, experience and role-relevant facts; never consider or infer age, gender, ethnicity, name origin, family status or other protected characteristics. A must-have with no evidence in the resume counts against the score.\n\n".$v('job_description'),
        ]),
        $ai('invite', 'Write a short, friendly email inviting the candidate to a first interview, asking for their availability this week. Under 90 words.', 'Role summary:'."\n".$v('job_description')),
        $step('draft_invite', 'gmail_create_draft', ['to' => $v('candidate_email'), 'subject' => 'Interview invitation', 'body' => $t('invite')]),
        $output('result', 'screening', "Score {{ nodes.screen.score }}/{{ nodes.screen.max_score }} ({{ nodes.screen.verdict }}).\nStrengths: {{ nodes.screen.strengths }}\nGaps to verify: {{ nodes.screen.gaps }}\nThis supports a human decision; it does not make one."),
    ], $edges(
        ['job_description', 'screen'], ['resume', 'screen'], ['candidate_email', 'screen'],
        ['screen', 'invite', 'strong'], ['screen', 'result', 'moderate'], ['screen', 'result', 'weak'],
        ['invite', 'draft_invite'], ['draft_invite', 'result'],
    )),
];

$templates['new-hire-onboarding-kickoff'] = [
    'name' => 'New hire onboarding kickoff',
    'description' => 'Creates a first-week schedule and 30-60-90 plan, emails it to the manager and welcomes the new hire in Slack.',
    'category' => 'HR & Recruiting',
    'icon' => 'calendar-days',
    'color' => 'green',
    'graph' => $graph([
        $input('hire_name', 'New hire name'),
        $input('role', 'Role and team'),
        $input('start_date', 'Start date'),
        $input('manager_email', 'Manager email'),
        $ai('plan', "You build onboarding plans: a first-week schedule (accounts and equipment, introductions, training, a first small task), goals for days 1-30, 31-60 and 61-90 with a measurable outcome each, the people to meet, and manager check-in questions for weeks 1, 4 and 12. Mark anything unknown as 'to confirm'. {$ONLY_FACTS}", $v('hire_name').', '.$v('role').', starting '.$v('start_date')),
        $step('email_manager', 'gmail_send_email', ['to' => $v('manager_email'), 'subject' => 'Onboarding plan for '.$v('hire_name'), 'body' => $t('plan')]),
        $step('welcome', 'slack_post_message', ['channel' => '#general', 'text' => 'Please welcome '.$v('hire_name').', joining as '.$v('role').' on '.$v('start_date').'.']),
    ], $edges(['hire_name', 'plan'], ['role', 'plan'], ['start_date', 'plan'], ['manager_email', 'plan'], ['plan', 'email_manager'], ['plan', 'welcome'])),
];

$templates['interview-notes-to-scorecard'] = [
    'name' => 'Interview notes to scorecard',
    'description' => 'Converts rough interviewer notes into a structured scorecard with evidence per competency and logs it to a sheet.',
    'category' => 'HR & Recruiting',
    'icon' => 'clipboard-list',
    'color' => 'orange',
    'graph' => $graph([
        $input('candidate', 'Candidate name'),
        $input('competencies', 'Competencies to rate', 'text', true, 'One per line, e.g. problem solving, communication.'),
        $input('notes', 'Interview notes', 'text'),
        $input('spreadsheet_id', 'Hiring tracker Sheet ID'),
        $ai('scorecard', "You write interview scorecards. For each competency give a 1 to 4 rating with the evidence from the notes, then an overall recommendation (strong yes, yes, no, strong no) and follow-up questions. Rate only on job-related evidence; never use protected characteristics. If the notes lack evidence for a competency, say 'insufficient evidence'. {$ONLY_FACTS}", "Competencies:\n".$v('competencies')."\n\nNotes:\n".$v('notes')),
        $step('log', 'google_sheets_append_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => 'Scorecards!A:B', 'values' => ['{{ nodes.candidate.value }}', '{{ nodes.scorecard.text }}']]),
        $output('result', 'scorecard', $t('scorecard')),
    ], $edges(['candidate', 'scorecard'], ['competencies', 'scorecard'], ['notes', 'scorecard'], ['spreadsheet_id', 'scorecard'], ['scorecard', 'log'], ['log', 'result'])),
];

/* ---------------------------------------------------------------- Finance */

$templates['invoice-capture-and-approval'] = [
    'name' => 'Invoice capture and approval',
    'description' => 'Reads the details off an invoice, asks a person to approve it, then adds a row to your accounts sheet with the vendor, dates and total.',
    'category' => 'Finance & Accounting',
    'icon' => 'receipt',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $input('invoice_text', 'Invoice text', 'text'),
        $input('spreadsheet_id', 'Accounts payable Sheet ID'),
        $step('invoice', 'ai_extract', [
            'text' => $v('invoice_text'),
            'fields' => [
                'vendor' => 'the vendor or supplier name',
                'invoice_number' => 'the invoice number',
                'invoice_date' => 'the invoice date',
                'due_date' => 'the payment due date',
                'currency' => 'the three-letter currency code',
                'subtotal' => 'the subtotal as a number',
                'tax' => 'the tax amount as a number',
                'total' => 'the total amount due as a number',
                'totals_match' => 'true if the line items add up to the subtotal and the subtotal plus tax equals the total, false if they do not, null if you cannot tell',
            ],
        ]),
        $step('approve', 'human_approval'),
        $step('log', 'google_sheets_append_values', [
            'spreadsheet_id' => $v('spreadsheet_id'),
            'range' => 'Invoices!A:H',
            'values' => ['{{ nodes.invoice.vendor }}', '{{ nodes.invoice.invoice_number }}', '{{ nodes.invoice.invoice_date }}', '{{ nodes.invoice.due_date }}', '{{ nodes.invoice.currency }}', '{{ nodes.invoice.subtotal }}', '{{ nodes.invoice.tax }}', '{{ nodes.invoice.total }}'],
        ]),
        $output('result', 'invoice_details', '{{ nodes.invoice.data }}'),
        $output('missing', 'missing_fields', '{{ nodes.invoice.missing }}'),
    ], $edges(['invoice_text', 'invoice'], ['spreadsheet_id', 'invoice'], ['invoice', 'approve'], ['approve', 'log'], ['log', 'result'], ['log', 'missing'])),
];

$templates['overdue-invoice-reminder'] = [
    'name' => 'Overdue invoice reminder',
    'description' => 'Writes a reminder whose tone matches how late the invoice is and sends it from Gmail once a person approves it.',
    'category' => 'Finance & Accounting',
    'icon' => 'mail',
    'color' => 'orange',
    'graph' => $graph([
        $input('customer_name', 'Customer name'),
        $input('customer_email', 'Customer email'),
        $input('invoice_number', 'Invoice number'),
        $input('amount', 'Amount due (with currency)'),
        $input('days_overdue', 'Days overdue', 'number'),
        $input('payment_link', 'Payment link', 'string', false),
        $ai('reminder', "You write overdue-invoice reminders under 120 words. 1 to 7 days: a friendly nudge. 8 to 30 days: a clear reminder restating amount and how to pay. Over 30 days: a firm notice asking for payment or a reply within 7 days. Professional, never threatening. Do not mention late fees or legal action. {$ONLY_FACTS}", 'Customer: '.$v('customer_name')."\nInvoice: ".$v('invoice_number')."\nAmount: ".$v('amount')."\nDays overdue: ".$v('days_overdue')."\nPayment link: ".$v('payment_link')),
        $step('approve', 'human_approval'),
        $step('send', 'gmail_send_email', ['to' => $v('customer_email'), 'subject' => 'Invoice '.$v('invoice_number').' is overdue', 'body' => $t('reminder')]),
    ], $edges(['customer_name', 'reminder'], ['customer_email', 'reminder'], ['invoice_number', 'reminder'], ['amount', 'reminder'], ['days_overdue', 'reminder'], ['payment_link', 'reminder'], ['reminder', 'approve'], ['approve', 'send'])),
];

$templates['expense-policy-review'] = [
    'name' => 'Expense report policy review',
    'description' => 'Checks each expense line against your policy, flags missing receipts and duplicates, and sends the finance team a decision summary.',
    'category' => 'Finance & Accounting',
    'icon' => 'receipt',
    'color' => 'green',
    'graph' => $graph([
        $input('policy', 'Expense policy', 'text'),
        $input('expenses', 'Expense lines', 'text', true, 'One per line: date, category, amount, description, receipt yes/no.'),
        $input('finance_email', 'Finance team email'),
        $ai('review', "You review expense reports against the policy supplied. For each line mark approve, query (with the question to ask) or reject (quoting the policy clause). Flag duplicates, missing receipts and amounts over category limits. End with totals: amount approved and amount held. If the policy is silent on a line, mark it query. {$ONLY_FACTS}", "Policy:\n".$v('policy')."\n\nExpenses:\n".$v('expenses')),
        $step('send', 'gmail_send_email', ['to' => $v('finance_email'), 'subject' => 'Expense review', 'body' => $t('review')]),
    ], $edges(['policy', 'review'], ['expenses', 'review'], ['finance_email', 'review'], ['review', 'send'])),
];

$templates['monthly-financial-summary'] = [
    'name' => 'Monthly financial summary for leadership',
    'description' => 'Explains a month’s P&L figures in plain language with the biggest movements and questions to ask, and emails it to leadership.',
    'category' => 'Finance & Accounting',
    'icon' => 'chart-line',
    'color' => 'purple',
    'graph' => $graph([
        $input('figures', 'This month’s figures', 'text', true, 'Revenue, costs, profit, cash and anything else you track.'),
        $input('previous', 'Last month’s figures', 'text', false),
        $input('recipients', 'Send to (email)'),
        $ai('summary', "You explain financial results to non-finance leaders. Give a three-sentence summary, a table of each metric with change against last month (absolute and percent), the two or three biggest movements in plain words, risks, and questions to ask finance. Show your calculations. State once at the end that this is analysis, not accounting advice. {$ONLY_FACTS}", "This month:\n".$v('figures')."\n\nLast month:\n".$v('previous')),
        $step('send', 'gmail_send_email', ['to' => $v('recipients'), 'subject' => 'Monthly financial summary', 'body' => $t('summary')]),
    ], $edges(['figures', 'summary'], ['previous', 'summary'], ['recipients', 'summary'], ['summary', 'send'])),
];

/* -------------------------------------------------------------- E-commerce */

$templates['shopify-order-issue-resolution'] = [
    'name' => 'Shopify order issue resolution',
    'description' => 'Looks up the order, decides a resolution under your policy, and sends the customer a reply once a person approves it.',
    'category' => 'E-commerce',
    'icon' => 'package',
    'color' => 'orange',
    'is_featured' => true,
    'graph' => $graph([
        $input('order_id', 'Shopify order ID'),
        $input('customer_email', 'Customer email'),
        $input('complaint', 'Customer message', 'text'),
        $input('policy', 'Shipping and returns policy', 'text'),
        $step('order', 'shopify_get_order', ['order_id' => $v('order_id')]),
        $ai('resolution', "You resolve order problems. Using the order data and the store policy, identify the issue (late, lost, damaged, wrong item, missing item, change request), choose the resolution the policy allows (reship, refund, partial refund, replacement, store credit), and write a warm reply under 120 words that states it and the next step. Note any evidence to request and any fraud signals for a human to check. If the policy does not cover it, say 'ESCALATE' and summarise instead. {$ONLY_FACTS}", "Policy:\n".$v('policy')."\n\nOrder:\n{{ nodes.order }}\n\nCustomer message:\n".$v('complaint')),
        $step('approve', 'human_approval'),
        $step('send', 'gmail_send_email', ['to' => $v('customer_email'), 'subject' => 'About your order', 'body' => $t('resolution')]),
    ], $edges(['order_id', 'order'], ['customer_email', 'order'], ['complaint', 'order'], ['policy', 'order'], ['order', 'resolution'], ['resolution', 'approve'], ['approve', 'send'])),
];

$templates['new-product-listing-draft'] = [
    'name' => 'New product listing draft',
    'description' => 'Writes the title and description from product facts and creates the product in Shopify as a draft for you to review.',
    'category' => 'E-commerce',
    'icon' => 'shopping-cart',
    'color' => 'blue',
    'graph' => $graph([
        $input('facts', 'Product facts', 'text', true, 'Materials, dimensions, features, who it is for.'),
        $input('price', 'Price', 'number'),
        $input('vendor', 'Vendor or brand', 'string', false),
        $ai('title', 'Write a product title under 70 characters that leads with what the product is. Reply with the title only.', $v('facts')),
        $ai('description', "You write e-commerce product copy as HTML: a two-sentence summary, five benefit-led bullets (<ul>), and a short paragraph on care or sizing. Do not claim certifications, materials or results that are not stated. {$ONLY_FACTS}", $v('facts')),
        $step('create', 'shopify_create_product', ['title' => $t('title'), 'body_html' => $t('description'), 'vendor' => $v('vendor'), 'price' => $v('price'), 'status' => 'draft']),
    ], $edges(['facts', 'title'], ['price', 'title'], ['vendor', 'title'], ['title', 'description'], ['description', 'create'])),
];

$templates['daily-shopify-orders-digest'] = [
    'name' => 'Daily Shopify orders digest',
    'description' => 'Each morning summarises open unfulfilled orders and what needs attention, and posts it to Slack.',
    'category' => 'E-commerce',
    'icon' => 'shopping-cart',
    'color' => 'green',
    'graph' => $graph([
        $step('morning', 'schedule_trigger', ['cron' => '0 8 * * *']),
        $step('orders', 'shopify_list_orders', ['status' => 'open', 'fulfillment_status' => 'unfulfilled', 'limit' => 50]),
        $ai('digest', "You summarise e-commerce orders for an operations team: how many orders await fulfilment, the oldest, total value, any unusual order (very large, repeated customer, missing address) and a short action list. Use only the order data. {$ONLY_FACTS}", "Unfulfilled orders:\n{{ nodes.orders }}"),
        $step('post', 'slack_post_message', ['channel' => '#operations', 'text' => "*Orders to fulfil*\n".$t('digest')]),
    ], $edges(['morning', 'orders'], ['orders', 'digest'], ['digest', 'post'])),
];

/* ---------------------------------------------------------- Engineering & IT */

$templates['pull-request-review-summary'] = [
    'name' => 'Pull request review summary',
    'description' => 'Fetches the files in a pull request, has AI review them for bugs and security issues, and posts the findings to Slack.',
    'category' => 'Engineering & IT',
    'icon' => 'code',
    'color' => 'blue',
    'graph' => $graph([
        $input('repo', 'Repository (owner/name)'),
        $input('pull_number', 'Pull request number', 'number'),
        $step('files', 'github_list_pull_request_files', ['repo' => $v('repo'), 'pull_number' => $v('pull_number')]),
        $ai('review', "You review code changes. Summarise the change in two sentences, then list problems by severity (bugs and security first, then performance, then readability and tests) with file, why it matters and a concrete fix. Note missing tests. Skip formatter-level nitpicks and say 'no issues found' rather than inventing some. {$ONLY_FACTS}", "Pull request files with patches:\n{{ nodes.files.files }}"),
        $step('post', 'slack_post_message', ['channel' => '#engineering', 'text' => 'Review of '.$v('repo').' #'.$v('pull_number')."\n".$t('review')]),
    ], $edges(['repo', 'files'], ['pull_number', 'files'], ['files', 'review'], ['review', 'post'])),
];

$templates['incident-postmortem-to-doc'] = [
    'name' => 'Incident postmortem to Google Doc',
    'description' => 'Turns incident notes and a timeline into a blameless postmortem, saves it as a Google Doc and tells the team in Slack.',
    'category' => 'Engineering & IT',
    'icon' => 'shield-check',
    'color' => 'red',
    'graph' => $graph([
        $input('title', 'Incident title'),
        $input('notes', 'Notes, chat logs and timeline', 'text'),
        $ai('postmortem', "You write blameless postmortems: Summary and impact, a UTC timeline (detection, escalation, mitigation, resolution), root cause and contributing factors focused on systems and process, what went well, what went poorly, and action items with owner, priority and due date that prevent recurrence or speed detection. Mark anything uncertain 'to confirm'. {$ONLY_FACTS}", $v('title')."\n\n".$v('notes')),
        $step('doc', 'google_docs_create_document', ['title' => 'Postmortem: '.$v('title'), 'content' => $t('postmortem')]),
        $step('announce', 'slack_post_message', ['channel' => '#engineering', 'text' => 'Postmortem draft ready: '.$v('title')."\nPlease review the document in Google Docs."]),
    ], $edges(['title', 'postmortem'], ['notes', 'postmortem'], ['postmortem', 'doc'], ['doc', 'announce'])),
];

$templates['it-request-to-jira'] = [
    'name' => 'IT request to Jira ticket',
    'description' => 'Turns an employee’s free-text IT request into a clear Jira ticket, and flags suspected security issues to the security channel first.',
    'category' => 'Engineering & IT',
    'icon' => 'wrench',
    'color' => 'teal',
    'graph' => $graph([
        $input('project', 'Jira project key'),
        $input('requester', 'Requester email'),
        $input('request', 'What is the problem?', 'text'),
        $step('ticket_text', 'ai_extract', [
            'text' => 'Requester: '.$v('requester')."\n".$v('request'),
            'fields' => [
                'summary' => 'a Jira summary under 90 characters',
                'description' => 'the request as a clear ticket description: what the user needs, device and operating system if given, error messages quoted exactly, and what has been tried',
            ],
        ]),
        $step('kind', 'ai_classify', [
            'text' => $v('request'),
            'categories' => ['security', 'other'],
            'guidance' => 'security: suspected phishing, malware, a lost or stolen device, or exposed credentials. other: everything else.',
        ]),
        $step('alert', 'slack_post_message', ['channel' => '#security', 'text' => 'Possible security issue reported by '.$v('requester').":\n{{ nodes.ticket_text.description }}\n{{ nodes.kind.reason }}"]),
        $step('ticket', 'jira_create_issue', ['project' => $v('project'), 'issue_type' => 'Task', 'summary' => '{{ nodes.ticket_text.summary }}', 'description' => '{{ nodes.ticket_text.description }}']),
    ], $edges(['project', 'ticket_text'], ['requester', 'ticket_text'], ['request', 'ticket_text'], ['request', 'kind'], ['ticket_text', 'ticket'], ['kind', 'alert', 'security'], ['kind', 'ticket', 'other'], ['alert', 'ticket'])),
];

/* ---------------------------------------------------- Legal & Compliance */

$templates['contract-risk-review'] = [
    'name' => 'Contract risk review',
    'description' => 'Summarises key terms, flags one-sided clauses with suggested edits, and emails the review to whoever requested it.',
    'category' => 'Legal & Compliance',
    'icon' => 'scale',
    'color' => 'blue',
    'graph' => $graph([
        $input('contract', 'Contract text', 'text'),
        $input('priorities', 'What matters most to you', 'text', false, 'e.g. liability cap, payment terms, exit rights.'),
        $input('requester_email', 'Send the review to'),
        $ai('review', "You help non-lawyers review contracts. Give a table of parties, term, price and payment, renewal, termination and liability. Flag unusual or one-sided clauses (auto-renewal, unlimited liability, broad indemnity, IP assignment, exclusivity, non-compete, unilateral changes), quoting each, explaining the risk plainly and proposing what to negotiate. List missing protections. End with: 'This is a summary to prepare for review, not legal advice; consult a qualified lawyer before signing.' {$ONLY_FACTS}", 'Priorities: '.$v('priorities')."\n\nContract:\n".$v('contract')),
        $step('send', 'gmail_send_email', ['to' => $v('requester_email'), 'subject' => 'Contract review', 'body' => $t('review')]),
    ], $edges(['contract', 'review'], ['priorities', 'review'], ['requester_email', 'review'], ['review', 'send'])),
];

$templates['compliance-gap-check'] = [
    'name' => 'Policy compliance gap check',
    'description' => 'Compares a policy document with a compliance framework such as GDPR or SOC 2, lists the gaps by risk, and files the findings in Notion.',
    'category' => 'Legal & Compliance',
    'icon' => 'shield-check',
    'color' => 'green',
    'graph' => $graph([
        $input('framework', 'Framework', 'string', true, 'e.g. GDPR, SOC 2, HIPAA.'),
        $input('document', 'Policy or process document', 'text'),
        $input('notion_parent_page', 'Notion page to save under'),
        $ai('gaps', "You check documents against a compliance framework. Map the document to the framework's relevant requirements, mark each covered, partly covered or missing with the supporting quote, prioritise gaps by risk with suggested wording or controls, and list the evidence an auditor would ask for. State that this supports, and does not replace, review by a compliance professional. {$ONLY_FACTS}", 'Framework: '.$v('framework')."\n\n".$v('document')),
        $step('save', 'notion_create_page', ['parent_page' => $v('notion_parent_page'), 'title' => 'Gap check: '.$v('framework'), 'content' => $t('gaps')]),
        $output('result', 'gaps', $t('gaps')),
    ], $edges(['framework', 'gaps'], ['document', 'gaps'], ['notion_parent_page', 'gaps'], ['gaps', 'save'], ['save', 'result'])),
];

/* ------------------------------------------------------------ Real Estate */

$templates['property-listing-package'] = [
    'name' => 'Property listing package',
    'description' => 'Creates the listing description, feature bullets and a social post from property facts, following fair-housing rules, and saves them to a Google Doc.',
    'category' => 'Real Estate',
    'icon' => 'home',
    'color' => 'blue',
    'graph' => $graph([
        $input('facts', 'Property facts', 'text', true, 'Type, size, bedrooms, bathrooms, features, neighbourhood, price.'),
        $ai('listing', "You write real-estate listings: a headline under 80 characters, a 150-word description opening with the strongest feature, key feature bullets, and a short social post. Follow fair-housing rules: never describe the type of buyer or neighbours or imply a preference by family status, race, religion or similar. {$ONLY_FACTS}", $v('facts')),
        $step('doc', 'google_docs_create_document', ['title' => 'Listing draft', 'content' => $t('listing')]),
        $output('result', 'listing', $t('listing')),
    ], $edges(['facts', 'listing'], ['listing', 'doc'], ['doc', 'result'])),
];

$templates['showing-follow-up'] = [
    'name' => 'Showing follow-up sequence',
    'description' => 'Drafts a same-day follow-up to a buyer lead after a showing, with later messages, saved as Gmail drafts for the agent to personalise.',
    'category' => 'Real Estate',
    'icon' => 'mail',
    'color' => 'green',
    'graph' => $graph([
        $input('lead_email', 'Lead email'),
        $input('lead_name', 'Lead name'),
        $input('property', 'Property viewed'),
        $input('feedback', 'What they said', 'text', false),
        $ai('followup', "You write follow-ups for real-estate agents. Write a same-day message referencing something specific they liked or asked, and a ten-day message that adds value. Each under 90 words, conversational, with one call to action. Do not share other clients' information or state market predictions as fact. {$ONLY_FACTS}", 'Lead: '.$v('lead_name')."\nProperty: ".$v('property')."\nFeedback: ".$v('feedback')),
        $step('draft', 'gmail_create_draft', ['to' => $v('lead_email'), 'subject' => 'Following up on '.$v('property'), 'body' => $t('followup')]),
    ], $edges(['lead_email', 'followup'], ['lead_name', 'followup'], ['property', 'followup'], ['feedback', 'followup'], ['followup', 'draft'])),
];

/* -------------------------------------------------------------- Education */

$templates['lesson-plan-to-doc'] = [
    'name' => 'Lesson plan to Google Doc',
    'description' => 'Generates a timed lesson plan with objectives, differentiation and an exit assessment, and saves it as a Google Doc.',
    'category' => 'Education',
    'icon' => 'graduation-cap',
    'color' => 'blue',
    'graph' => $graph([
        $input('subject', 'Subject and topic'),
        $input('level', 'Grade or level'),
        $input('minutes', 'Lesson length (minutes)', 'number'),
        $input('goals', 'Learning goals', 'text', false),
        $ai('plan', "You design lesson plans: two or three measurable objectives, timed segments (warm-up, instruction, guided practice, independent work, wrap-up), differentiation for learners who need support and those ready for extension, a short formative assessment and a homework idea. Keep it realistic for the time. {$ONLY_FACTS}", $v('subject').' for '.$v('level').', '.$v('minutes')." minutes.\nGoals: ".$v('goals')),
        $step('doc', 'google_docs_create_document', ['title' => 'Lesson plan: '.$v('subject'), 'content' => $t('plan')]),
        $output('result', 'plan', $t('plan')),
    ], $edges(['subject', 'plan'], ['level', 'plan'], ['minutes', 'plan'], ['goals', 'plan'], ['plan', 'doc'], ['doc', 'result'])),
];

$templates['quiz-from-material'] = [
    'name' => 'Quiz from course material',
    'description' => 'Writes a quiz with an answer key and explanations from the material you paste, and emails it to the teacher.',
    'category' => 'Education',
    'icon' => 'clipboard-list',
    'color' => 'purple',
    'graph' => $graph([
        $input('material', 'Source material', 'text'),
        $input('questions', 'Number of questions', 'number'),
        $input('teacher_email', 'Send the quiz to'),
        $ai('quiz', "You write quizzes from the supplied material only: a mix of multiple-choice (with plausible distractors), true/false and short-answer questions covering it evenly, then an answer key with a one-sentence explanation for each. Tag each question with topic and difficulty. Do not test facts that are not in the material. {$ONLY_FACTS}", 'Questions: '.$v('questions')."\n\nMaterial:\n".$v('material')),
        $step('send', 'gmail_send_email', ['to' => $v('teacher_email'), 'subject' => 'Your quiz draft', 'body' => $t('quiz')]),
    ], $edges(['material', 'quiz'], ['questions', 'quiz'], ['teacher_email', 'quiz'], ['quiz', 'send'])),
];

$templates['assignment-feedback-draft'] = [
    'name' => 'Assignment feedback drafts',
    'description' => 'Scores a submission against the rubric with examples from the work and drafts feedback for the teacher to review before sending.',
    'category' => 'Education',
    'icon' => 'file-text',
    'color' => 'green',
    'graph' => $graph([
        $input('assignment', 'Assignment brief', 'text'),
        $input('rubric', 'Rubric', 'text'),
        $input('submission', 'Student submission', 'text'),
        $input('teacher_email', 'Send the draft to'),
        $ai('feedback', "You give feedback on student work: score each rubric criterion with an example from the work, name two strengths and the two most valuable improvements as next steps, and write a short encouraging comment to the student at an age-appropriate reading level. Be specific and kind. Mark it as a draft for the teacher. {$ONLY_FACTS}", "Assignment:\n".$v('assignment')."\n\nRubric:\n".$v('rubric')."\n\nSubmission:\n".$v('submission')),
        $step('send', 'gmail_send_email', ['to' => $v('teacher_email'), 'subject' => 'Feedback draft for review', 'body' => $t('feedback')]),
    ], $edges(['assignment', 'feedback'], ['rubric', 'feedback'], ['submission', 'feedback'], ['teacher_email', 'feedback'], ['feedback', 'send'])),
];

/* --------------------------------------------------------- Healthcare Admin */

$templates['appointment-reminder-sms'] = [
    'name' => 'Appointment reminder by SMS',
    'description' => 'Sends a short, privacy-safe appointment reminder by text message with how to confirm or reschedule.',
    'category' => 'Healthcare Admin',
    'icon' => 'calendar-days',
    'color' => 'teal',
    'graph' => $graph([
        $input('phone', 'Patient mobile number'),
        $input('practice', 'Practice name'),
        $input('when', 'Date and time'),
        $input('where', 'Location'),
        $input('contact', 'Number or link to confirm or reschedule'),
        $ai('message', "You write appointment reminders for a clinic as one text message under 160 characters. Include the practice, date, time, place and how to confirm or reschedule. Never include diagnoses, treatments, provider specialties that reveal conditions, or other health details. {$ONLY_FACTS}", 'Practice: '.$v('practice')."\nWhen: ".$v('when')."\nWhere: ".$v('where')."\nContact: ".$v('contact')),
        $step('send', 'twilio_send_sms', ['to' => $v('phone'), 'body' => $t('message')]),
    ], $edges(['phone', 'message'], ['practice', 'message'], ['when', 'message'], ['where', 'message'], ['contact', 'message'], ['message', 'send'])),
];

$templates['intake-form-front-desk-checklist'] = [
    'name' => 'Intake form front-desk checklist',
    'description' => 'Organises a completed intake form into a checklist of what the front desk must still collect, and sends it to the practice manager.',
    'category' => 'Healthcare Admin',
    'icon' => 'heart-pulse',
    'color' => 'blue',
    'graph' => $graph([
        $input('intake', 'Completed intake form', 'text'),
        $input('manager_email', 'Practice manager email'),
        $ai('checklist', "You organise patient intake paperwork for clinic administrative staff. Summarise demographics, insurance details, the reason for visit as stated and missing forms; flag incomplete or inconsistent fields (missing consent, expired insurance); and output a checklist of what the front desk must collect before the appointment. You do not diagnose, give medical advice or interpret symptoms. If the form describes an emergency, tell staff to follow the clinic's emergency procedure immediately. {$ONLY_FACTS}", $v('intake')),
        $step('send', 'gmail_send_email', ['to' => $v('manager_email'), 'subject' => 'Intake checklist', 'body' => $t('checklist')]),
    ], $edges(['intake', 'checklist'], ['manager_email', 'checklist'], ['checklist', 'send'])),
];

/* ---------------------------------------------------- Productivity & Ops */

$templates['meeting-notes-to-actions'] = [
    'name' => 'Meeting notes to summary and actions',
    'description' => 'Turns a transcript into decisions and an owner-assigned action list, posts it to Slack and saves it in Notion.',
    'category' => 'Productivity & Ops',
    'icon' => 'file-text',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $input('title', 'Meeting title'),
        $input('transcript', 'Transcript or notes', 'text'),
        $input('notion_parent_page', 'Notion page to save under'),
        $step('notes', 'ai_summarize', ['text' => $v('title')."\n\n".$v('transcript'), 'style' => 'actions', 'length' => 'medium', 'focus' => 'decisions, owners and due dates; mark a missing owner or date as unassigned or no date rather than guessing']),
        $step('post', 'slack_post_message', ['channel' => '#team', 'text' => '*'.$v('title')."*\n{{ nodes.notes.summary }}"]),
        $step('save', 'notion_create_page', ['parent_page' => $v('notion_parent_page'), 'title' => $v('title'), 'content' => '{{ nodes.notes.summary }}']),
    ], $edges(['title', 'notes'], ['transcript', 'notes'], ['notion_parent_page', 'notes'], ['notes', 'post'], ['notes', 'save'])),
];

$templates['weekly-team-update'] = [
    'name' => 'Weekly team update',
    'description' => 'Turns the week’s notes, tickets and wins into a four-section team update and emails it to the team.',
    'category' => 'Productivity & Ops',
    'icon' => 'calendar-days',
    'color' => 'purple',
    'graph' => $graph([
        $input('notes', 'This week’s notes, tickets and wins', 'text'),
        $input('team_email', 'Team email address'),
        $ai('update', "You write the team's weekly update with four short bulleted sections: Shipped, In progress (owner and expected date, or say if unknown), Blockers and decisions needed, and Wins. Under one page. {$ONLY_FACTS}", $v('notes')),
        $step('send', 'gmail_send_email', ['to' => $v('team_email'), 'subject' => 'Weekly update', 'body' => $t('update')]),
    ], $edges(['notes', 'update'], ['team_email', 'update'], ['update', 'send'])),
];

$templates['sop-from-process-description'] = [
    'name' => 'SOP from a process description',
    'description' => 'Converts a rough description of how a task is done into a standard operating procedure and saves it as a Google Doc.',
    'category' => 'Productivity & Ops',
    'icon' => 'clipboard-list',
    'color' => 'teal',
    'graph' => $graph([
        $input('process_name', 'Process name'),
        $input('description', 'How it is done today', 'text'),
        $ai('sop', "You write standard operating procedures: purpose, scope, owner and required tools or access; numbered steps that start with a verb and state the expected result; common mistakes and what to do when something goes wrong; and open questions. Do not fill gaps with invented steps; mark them 'to confirm'. {$ONLY_FACTS}", $v('process_name')."\n\n".$v('description')),
        $step('doc', 'google_docs_create_document', ['title' => 'SOP: '.$v('process_name'), 'content' => $t('sop')]),
        $output('result', 'sop', $t('sop')),
    ], $edges(['process_name', 'sop'], ['description', 'sop'], ['sop', 'doc'], ['doc', 'result'])),
];

/* ------------------------------------------------- Built on the AI nodes */

$templates['ticket-classify-and-route'] = [
    'name' => 'Ticket classify and route',
    'description' => 'Sorts each incoming request into billing, bug, how-to, feature request or account access, and posts it to the channel of the team that owns it.',
    'category' => 'Support & Success',
    'icon' => 'headphones',
    'color' => 'teal',
    'is_featured' => true,
    'graph' => $graph([
        $input('customer', 'Customer'),
        $input('subject', 'Subject'),
        $input('message', 'Message', 'text'),
        $step('kind', 'ai_classify', [
            'text' => $v('subject')."\n".$v('message'),
            'categories' => ['billing', 'bug', 'how-to', 'feature request', 'account access'],
            'guidance' => 'billing: charges, invoices, refunds, plans. bug: something is broken or erroring. how-to: asking how to do something. feature request: asking for something that does not exist. account access: sign-in, password, permissions.',
            'allow_other' => true,
        ]),
        $step('billing', 'slack_post_message', ['channel' => '#support-billing', 'text' => 'Billing request from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $step('bug', 'slack_post_message', ['channel' => '#support-bugs', 'text' => 'Possible bug from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $step('howto', 'slack_post_message', ['channel' => '#support-help', 'text' => 'Question from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $step('feature', 'slack_post_message', ['channel' => '#product-feedback', 'text' => 'Feature request from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $step('access', 'slack_post_message', ['channel' => '#support-access', 'text' => 'Access issue from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $step('other', 'slack_post_message', ['channel' => '#support', 'text' => 'Unsorted request from '.$v('customer').': '.$v('subject')."\n".$v('message')]),
        $output('routed', 'category', '{{ nodes.kind.label }} ({{ nodes.kind.confidence }})'),
    ], $edges(
        ['customer', 'kind'], ['subject', 'kind'], ['message', 'kind'],
        ['kind', 'billing', 'billing'], ['kind', 'bug', 'bug'], ['kind', 'howto', 'how-to'], ['kind', 'feature', 'feature request'], ['kind', 'access', 'account access'], ['kind', 'other', 'other'],
        ['billing', 'routed'], ['bug', 'routed'], ['howto', 'routed'], ['feature', 'routed'], ['access', 'routed'], ['other', 'routed'],
    )),
];

$templates['review-sentiment-response'] = [
    'name' => 'Review sentiment and response',
    'description' => 'Reads the feeling in a customer review, alerts the team to negative ones with a draft apology, and drafts a thank-you for positive ones.',
    'category' => 'Support & Success',
    'icon' => 'star',
    'color' => 'purple',
    'graph' => $graph([
        $input('review', 'Review text', 'text'),
        $input('reviewer', 'Reviewer first name', 'string', false),
        $step('feeling', 'ai_sentiment', ['text' => $v('review'), 'about' => 'our product or service']),
        $ai('apology', "You reply to unhappy customers in under 80 words: apologise for the specific problem, say what you will do, give an offline way to reach support. Never defensive, never offer discounts or refunds, never reveal personal data. {$ONLY_FACTS}", 'Reviewer: '.$v('reviewer')."\nReview: ".$v('review')),
        $step('alert', 'slack_post_message', ['channel' => '#support', 'text' => "Negative review ({{ nodes.feeling.score }}) needs a person.\nFeeling: {{ nodes.feeling.emotions }}\nReview: ".$v('review')."\nDraft reply: ".$t('apology')]),
        $ai('thanks', "You reply to happy customers in under 60 words: thank them for something specific they said. Warm, genuine, no upsell. {$ONLY_FACTS}", 'Reviewer: '.$v('reviewer')."\nReview: ".$v('review')),
        $step('post_thanks', 'slack_post_message', ['channel' => '#reviews', 'text' => "Draft thank-you for a {{ nodes.feeling.sentiment }} review:\n".$t('thanks')]),
    ], $edges(
        ['review', 'feeling'], ['reviewer', 'feeling'],
        ['feeling', 'apology', 'negative'], ['feeling', 'apology', 'mixed'], ['feeling', 'thanks', 'positive'], ['feeling', 'thanks', 'neutral'],
        ['apology', 'alert'], ['thanks', 'post_thanks'],
    )),
];

$templates['multilingual-support-reply'] = [
    'name' => 'Multilingual support reply',
    'description' => 'Translates a customer’s message into your language, drafts the reply, and translates it back to theirs for a person to approve and send.',
    'category' => 'Support & Success',
    'icon' => 'mail',
    'color' => 'green',
    'graph' => $graph([
        $input('customer_email', 'Customer email'),
        $input('customer_language', 'Customer’s language'),
        $input('team_language', 'Your team’s language', 'string', true, 'e.g. English'),
        $input('message', 'Customer’s message', 'text'),
        $input('help_content', 'Help-center content and policies', 'text', false),
        $step('inbound', 'ai_translate', ['text' => $v('message'), 'target_language' => $v('team_language'), 'source_language' => $v('customer_language')]),
        $ai('draft', "You draft support replies for a human to approve. Acknowledge the problem, give numbered steps using only the supplied help content (if it does not cover it, say what you are checking), say what happens next. Never promise refunds, dates or features the policy does not state. {$ONLY_FACTS}", "Help content:\n".$v('help_content')."\n\nCustomer message:\n{{ nodes.inbound.translation }}"),
        $step('outbound', 'ai_translate', ['text' => $t('draft'), 'target_language' => $v('customer_language'), 'source_language' => $v('team_language'), 'tone' => 'friendly']),
        $step('approve', 'human_approval'),
        $step('send', 'gmail_send_email', ['to' => $v('customer_email'), 'subject' => 'Re: your message', 'body' => '{{ nodes.outbound.translation }}']),
    ], $edges(['customer_email', 'inbound'], ['customer_language', 'inbound'], ['team_language', 'inbound'], ['message', 'inbound'], ['help_content', 'inbound'], ['inbound', 'draft'], ['draft', 'outbound'], ['outbound', 'approve'], ['approve', 'send'])),
];

$templates['inbound-email-to-crm-contact'] = [
    'name' => 'Inbound email to CRM contact',
    'description' => 'Reads the sender’s details out of an email, creates the HubSpot contact and posts a one-line summary of the request to Slack.',
    'category' => 'Sales & Outreach',
    'icon' => 'mail',
    'color' => 'orange',
    'graph' => $graph([
        $input('email_text', 'Email, including the signature', 'text'),
        $step('sender', 'ai_extract', [
            'text' => $v('email_text'),
            'fields' => ['email' => 'the sender’s email address', 'first_name' => 'first name', 'last_name' => 'last name', 'company' => 'company name', 'phone' => 'phone number', 'job_title' => 'job title', 'request' => 'what they want, in one sentence'],
        ]),
        $step('contact', 'hubspot_create_contact', ['email' => '{{ nodes.sender.email }}', 'first_name' => '{{ nodes.sender.first_name }}', 'last_name' => '{{ nodes.sender.last_name }}']),
        $step('post', 'slack_post_message', ['channel' => '#new-leads', 'text' => 'New contact: {{ nodes.sender.first_name }} {{ nodes.sender.last_name }}, {{ nodes.sender.job_title }} at {{ nodes.sender.company }}'."\n".'{{ nodes.sender.request }}'."\nNot found in the email: {{ nodes.sender.missing }}"]),
    ], $edges(['email_text', 'sender'], ['sender', 'contact'], ['contact', 'post'])),
];

/* ----------------------------------------- Automatic: webhooks and schedules */

$templates['webhook-lead-capture-and-routing'] = [
    'name' => 'Website form lead capture and routing',
    'description' => 'Starts when your website form posts to a private URL, reads out the lead’s details, saves them to HubSpot and tells Slack how urgent it is and why.',
    'category' => 'Sales & Outreach',
    'icon' => 'target',
    'color' => 'blue',
    'is_featured' => true,
    'graph' => $graph([
        $step('hook', 'webhook_trigger', ['authentication' => 'header_token', 'header_name' => 'X-Webhook-Token']),
        $step('lead', 'ai_extract', [
            'text' => "Form submission:\n{{ nodes.hook.payload }}",
            'fields' => ['email' => 'the sender’s email address', 'first_name' => 'first name', 'last_name' => 'last name', 'company' => 'company name', 'request' => 'what they are asking for, in one sentence'],
        ]),
        $step('urgency', 'ai_classify', [
            'text' => "Form submission:\n{{ nodes.hook.payload }}",
            'categories' => ['hot', 'warm', 'cold'],
            'guidance' => 'hot: asks for a demo, pricing, a quote or a call, or states a deadline. warm: interested but exploring. cold: a vague or unrelated message.',
        ]),
        $step('save', 'hubspot_create_contact', ['email' => '{{ nodes.lead.email }}', 'first_name' => '{{ nodes.lead.first_name }}', 'last_name' => '{{ nodes.lead.last_name }}']),
        $step('notify', 'slack_post_message', ['channel' => '#new-leads', 'text' => "New website lead ({{ nodes.urgency.label }})\n{{ nodes.lead.first_name }} {{ nodes.lead.last_name }}, {{ nodes.lead.company }} - {{ nodes.lead.email }}\n{{ nodes.lead.request }}\nWhy: {{ nodes.urgency.reason }}"]),
    ], $edges(['hook', 'lead'], ['hook', 'urgency'], ['lead', 'save'], ['save', 'notify'], ['urgency', 'notify'])),
];

$templates['failed-payment-recovery'] = [
    'name' => 'Failed payment recovery',
    'description' => 'When a payment fails, reads out who and why, drafts a helpful, non-accusatory email, and sends it once a person approves.',
    'category' => 'Finance & Accounting',
    'icon' => 'receipt',
    'color' => 'red',
    'is_featured' => true,
    'graph' => $graph([
        $step('hook', 'webhook_trigger', ['authentication' => 'signature']),
        $step('payment', 'ai_extract', [
            'text' => "Payment-failed event:\n{{ nodes.hook.payload }}",
            'fields' => ['email' => 'the customer’s email address', 'amount' => 'the amount with its currency', 'reason' => 'why the payment failed, in plain words'],
        ]),
        $ai('message', "You write payment-failure emails under 110 words: tell the customer a payment did not go through, why if known, how to update their card (say 'use the secure link below' and leave the placeholder [UPDATE LINK]), and that the service stays active for a few days. Warm, never accusatory, no threats or late fees. {$ONLY_FACTS}", "Amount: {{ nodes.payment.amount }}\nReason: {{ nodes.payment.reason }}"),
        $step('approve', 'human_approval'),
        $step('send', 'gmail_send_email', ['to' => '{{ nodes.payment.email }}', 'subject' => 'Your payment didn’t go through', 'body' => $t('message')]),
    ], $edges(['hook', 'payment'], ['payment', 'message'], ['message', 'approve'], ['approve', 'send'])),
];

$templates['shopify-order-risk-check'] = [
    'name' => 'New order risk check',
    'description' => 'Reviews each new Shopify order for fraud signals as it arrives and alerts the team in Slack, with the reasons, only when something looks off.',
    'category' => 'E-commerce',
    'icon' => 'shield-check',
    'color' => 'orange',
    'graph' => $graph([
        $step('hook', 'webhook_trigger', ['authentication' => 'signature']),
        $step('risk', 'ai_classify', [
            'text' => "Order:\n{{ nodes.hook.payload }}",
            'categories' => ['low', 'review'],
            'guidance' => 'review: mismatched billing and shipping countries, a very high value for a first order, many high-value items, a free email address with rush shipping, repeated failed attempts, or an address that looks malformed. Otherwise low. Never accuse anyone; this only flags an order for a person to look at.',
        ]),
        $step('alert', 'slack_post_message', ['channel' => '#order-review', 'text' => "Order needs a look ({{ nodes.risk.confidence }} confidence)\nWhy: {{ nodes.risk.reason }}"]),
    ], $edges(['hook', 'risk'], ['risk', 'alert', 'review'])),
];

$templates['refund-request-with-approval'] = [
    'name' => 'Refund request with approval',
    'description' => 'Checks a refund request against your policy, and only when it qualifies asks a person to approve it, issues the Stripe refund and confirms to the customer.',
    'category' => 'Finance & Accounting',
    'icon' => 'receipt',
    'color' => 'green',
    'graph' => $graph([
        $input('payment_intent_id', 'Stripe payment intent ID'),
        $input('customer_email', 'Customer email'),
        $input('reason', 'Customer’s reason', 'text'),
        $input('policy', 'Refund policy', 'text'),
        $step('assessment', 'ai_classify', [
            'text' => $v('reason'),
            'categories' => ['approve', 'decline', 'needs more info'],
            'guidance' => "Decide using only this refund policy, and never approve what it does not allow:\n".$v('policy'),
        ]),
        $step('approve', 'human_approval'),
        $step('refund', 'stripe_create_refund', ['payment_intent_id' => $v('payment_intent_id'), 'reason' => 'requested_by_customer']),
        $ai('confirm', 'Write a short, warm email confirming the refund has been issued and that it can take 5 to 10 business days to appear. Under 70 words.', 'Reason for refund: '.$v('reason')),
        $step('send', 'gmail_send_email', ['to' => $v('customer_email'), 'subject' => 'Your refund has been issued', 'body' => $t('confirm')]),
        $output('declined', 'outcome', '{{ nodes.assessment.label }}: {{ nodes.assessment.reason }}'),
    ], $edges(
        ['payment_intent_id', 'assessment'], ['customer_email', 'assessment'], ['reason', 'assessment'], ['policy', 'assessment'],
        ['assessment', 'approve', 'approve'], ['assessment', 'declined', 'decline'], ['assessment', 'declined', 'needs more info'],
        ['approve', 'refund'], ['refund', 'confirm'], ['confirm', 'send'],
    )),
];

$templates['daily-inbox-priorities'] = [
    'name' => 'Daily inbox priorities',
    'description' => 'Each weekday morning reads your unread email, sorts it into act today, reply this week and ignore, and sends you the list in Slack.',
    'category' => 'Productivity & Ops',
    'icon' => 'mail',
    'color' => 'orange',
    'graph' => $graph([
        $input('slack_user', 'Your Slack user'),
        $step('morning', 'schedule_trigger', ['cron' => '0 7 * * 1-5']),
        $step('inbox', 'gmail_list_messages', ['query' => 'is:unread newer_than:1d', 'max_results' => 30]),
        $ai('priorities', "You help someone clear their inbox. Sort the emails into: act today, reply this week, waiting on others, can ignore. For 'act today' give a one-line reason. Flag deadlines, payments and anything legal or security-related. End with counts per group. {$ONLY_FACTS}", "Unread emails:\n{{ nodes.inbox.messages }}"),
        $step('dm', 'slack_send_direct_message', ['user' => $v('slack_user'), 'text' => "*Today’s inbox*\n".$t('priorities')]),
    ], $edges(['morning', 'inbox'], ['inbox', 'priorities'], ['slack_user', 'priorities'], ['priorities', 'dm'])),
];

$templates['meeting-prep-from-calendar'] = [
    'name' => 'Meeting prep from your calendar',
    'description' => 'Looks at your upcoming meetings and sends a short prep note for each one: purpose, who is attending and questions to bring.',
    'category' => 'Productivity & Ops',
    'icon' => 'calendar-days',
    'color' => 'blue',
    'graph' => $graph([
        $input('your_email', 'Send prep notes to'),
        $step('morning', 'schedule_trigger', ['cron' => '0 7 * * 1-5']),
        $step('events', 'google_calendar_list_upcoming_events', ['minutes_before' => 600, 'skip_all_day' => true, 'skip_declined' => true, 'max_results' => 10]),
        $ai('prep', "You prepare someone for their meetings. For each event give: purpose in one line, who is attending, three questions or decisions to bring, and what to read beforehand if the description mentions documents. Say 'no agenda - consider asking for one' when there is no description. {$ONLY_FACTS}", "Today's meetings:\n{{ nodes.events }}"),
        $step('send', 'gmail_send_email', ['to' => $v('your_email'), 'subject' => 'Your meeting prep for today', 'body' => $t('prep')]),
    ], $edges(['morning', 'events'], ['events', 'prep'], ['your_email', 'prep'], ['prep', 'send'])),
];

$templates['weekly-kpi-digest-from-sheet'] = [
    'name' => 'Weekly KPI digest from a sheet',
    'description' => 'Every Monday reads your KPI sheet, explains what moved and why it might matter, and posts the digest to Slack.',
    'category' => 'Data & Analytics',
    'icon' => 'chart-line',
    'color' => 'purple',
    'graph' => $graph([
        $input('spreadsheet_id', 'KPI Sheet ID'),
        $input('range', 'Range', 'string', true, 'e.g. KPIs!A1:H20 with a header row and one row per week.'),
        $step('monday', 'schedule_trigger', ['cron' => '0 9 * * 1']),
        $step('kpis', 'google_sheets_get_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => $v('range')]),
        $ai('digest', "You turn metrics into insight. Write a one-sentence headline, the change for each metric versus the previous row (absolute and percent), likely drivers labelled as hypotheses, up to three recommended actions, and flag any value that looks like a data-quality error. Show the arithmetic. {$ONLY_FACTS}", "KPI table (first row is headers):\n{{ nodes.kpis }}"),
        $step('post', 'slack_post_message', ['channel' => '#leadership', 'text' => "*Weekly KPI digest*\n".$t('digest')]),
    ], $edges(['monday', 'kpis'], ['spreadsheet_id', 'kpis'], ['range', 'kpis'], ['kpis', 'digest'], ['digest', 'post'])),
];

$templates['at-risk-subscriptions-alert'] = [
    'name' => 'At-risk subscriptions alert',
    'description' => 'Every morning lists subscriptions that are past due, works out who to contact first, and posts the list to Slack for the success team.',
    'category' => 'Support & Success',
    'icon' => 'chart-line',
    'color' => 'red',
    'graph' => $graph([
        $step('morning', 'schedule_trigger', ['cron' => '0 8 * * *']),
        $step('past_due', 'stripe_list_subscriptions', ['status' => 'past_due', 'limit' => 50]),
        $ai('plan', "You help a customer-success team recover revenue. From the past-due subscriptions, rank who to contact first (largest amount, longest overdue, annual plans), give a one-line reason and a suggested approach for each, and end with the total at risk. If the list is empty reply 'No past-due subscriptions today.' {$ONLY_FACTS}", "Past-due subscriptions:\n{{ nodes.past_due }}"),
        $step('post', 'slack_post_message', ['channel' => '#customer-success', 'text' => "*Past-due subscriptions*\n".$t('plan')]),
    ], $edges(['morning', 'past_due'], ['past_due', 'plan'], ['plan', 'post'])),
];

$templates['trial-ending-outreach'] = [
    'name' => 'Trial ending outreach',
    'description' => 'Every day finds trials that are about to convert and gives the team a personalised outreach note for each so conversions are not left to chance.',
    'category' => 'Sales & Outreach',
    'icon' => 'calendar-days',
    'color' => 'green',
    'graph' => $graph([
        $step('daily', 'schedule_trigger', ['cron' => '0 9 * * *']),
        $step('trials', 'stripe_list_subscriptions', ['status' => 'trialing', 'limit' => 50]),
        $ai('outreach', "You help a sales and success team convert trials. From the trialing subscriptions, pick those ending within 3 days and for each write a two-sentence outreach note that offers help rather than pressure, plus what to check in their usage before sending. If none end within 3 days reply 'No trials ending soon.' {$ONLY_FACTS}", "Trialing subscriptions:\n{{ nodes.trials }}"),
        $step('post', 'slack_post_message', ['channel' => '#sales', 'text' => "*Trials ending soon*\n".$t('outreach')]),
    ], $edges(['daily', 'trials'], ['trials', 'outreach'], ['outreach', 'post'])),
];

$templates['daily-cash-snapshot'] = [
    'name' => 'Daily cash snapshot',
    'description' => 'Each morning reads your Stripe balance and yesterday’s charges and posts a short financial snapshot with anything unusual.',
    'category' => 'Finance & Accounting',
    'icon' => 'chart-line',
    'color' => 'teal',
    'graph' => $graph([
        $step('daily', 'schedule_trigger', ['cron' => '0 7 * * *']),
        $step('balance', 'stripe_get_balance'),
        $step('charges', 'stripe_list_charges', ['limit' => 100]),
        $ai('snapshot', "You write a short daily finance snapshot: current available and pending balance, count and total of recent charges, the largest charge, and anything unusual (spike in failures, refunds, one very large charge). Show numbers from the data only; if currency amounts are in cents, convert and say so. {$ONLY_FACTS}", "Balance:\n{{ nodes.balance }}\n\nRecent charges:\n{{ nodes.charges }}"),
        $step('post', 'slack_post_message', ['channel' => '#finance', 'text' => "*Daily cash snapshot*\n".$t('snapshot')]),
    ], $edges(['daily', 'balance'], ['daily', 'charges'], ['balance', 'snapshot'], ['charges', 'snapshot'], ['snapshot', 'post'])),
];

$templates['weekly-release-notes'] = [
    'name' => 'Weekly release notes from merged pull requests',
    'description' => 'Every Friday collects the closed pull requests and writes customer-friendly release notes plus an internal changelog.',
    'category' => 'Engineering & IT',
    'icon' => 'code',
    'color' => 'blue',
    'graph' => $graph([
        $input('repo', 'Repository (owner/name)'),
        $step('friday', 'schedule_trigger', ['cron' => '0 15 * * 5']),
        $step('prs', 'github_list_pull_requests', ['repo' => $v('repo'), 'state' => 'closed']),
        $ai('notes', "You write release notes from merged pull requests. Produce two sections: 'What’s new' for customers (plain language, grouped under New, Improved, Fixed; skip refactors, dependency bumps and internal tooling) and 'Internal changelog' (one line per PR with its number). Do not describe changes the pull requests do not make. {$ONLY_FACTS}", "Closed pull requests:\n{{ nodes.prs }}"),
        $step('doc', 'google_docs_create_document', ['title' => 'Release notes', 'content' => $t('notes')]),
        $step('post', 'slack_post_message', ['channel' => '#product', 'text' => "*This week’s release notes*\n".$t('notes')]),
    ], $edges(['friday', 'prs'], ['repo', 'prs'], ['prs', 'notes'], ['notes', 'doc'], ['notes', 'post'])),
];

$templates['survey-responses-to-insights'] = [
    'name' => 'Google Forms survey insights',
    'description' => 'Reads the latest Google Forms responses, finds the themes and sentiment, and saves a report as a Google Doc.',
    'category' => 'Data & Analytics',
    'icon' => 'clipboard-list',
    'color' => 'green',
    'graph' => $graph([
        $input('form_id', 'Google Form ID'),
        $step('responses', 'google_forms_list_responses', ['form_id' => $v('form_id'), 'max_results' => 100]),
        $ai('insights', "You analyse survey responses. Group open-ended answers into 5 to 8 themes with counts, give overall sentiment and sentiment per theme, pull two short quotes per theme with names and contact details removed, list the three most actionable improvements, and state the sample size and warn if it is too small to conclude anything. {$ONLY_FACTS}", "Responses:\n{{ nodes.responses }}"),
        $step('doc', 'google_docs_create_document', ['title' => 'Survey insights', 'content' => $t('insights')]),
        $output('result', 'insights', $t('insights')),
    ], $edges(['form_id', 'responses'], ['responses', 'insights'], ['insights', 'doc'], ['doc', 'result'])),
];

$templates['job-application-intake'] = [
    'name' => 'Job application intake and acknowledgement',
    'description' => 'Starts when an application is posted to its URL, summarises the candidate against the role, logs it in a sheet and drafts an acknowledgement.',
    'category' => 'HR & Recruiting',
    'icon' => 'users',
    'color' => 'purple',
    'graph' => $graph([
        $input('spreadsheet_id', 'Applicant tracker Sheet ID'),
        $step('hook', 'webhook_trigger', ['authentication' => 'header_token', 'header_name' => 'X-Webhook-Token']),
        $ai('summary', "You summarise job applications for a recruiter in five lines: name, current role, years of relevant experience, strongest relevant skills, and anything to verify. Do not consider or infer protected characteristics. {$ONLY_FACTS}", "Application:\n{{ nodes.hook.payload }}"),
        $ai('applicant_email', 'Reply with only the applicant’s email address from the application, or the word none.', "Application:\n{{ nodes.hook.payload }}"),
        $step('log', 'google_sheets_append_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => 'Applicants!A:B', 'values' => ['{{ nodes.applicant_email.text }}', '{{ nodes.summary.text }}']]),
        $ai('ack', 'Write a warm two-sentence acknowledgement thanking the applicant and saying the team will reply within a week. No promises about the outcome.', 'Application received.'),
        $step('draft', 'gmail_create_draft', ['to' => $t('applicant_email'), 'subject' => 'We received your application', 'body' => $t('ack')]),
    ], $edges(['hook', 'summary'], ['hook', 'applicant_email'], ['spreadsheet_id', 'summary'], ['summary', 'log'], ['applicant_email', 'log'], ['log', 'ack'], ['ack', 'draft'])),
];

$templates['support-sla-breach-watch'] = [
    'name' => 'Support backlog check',
    'description' => 'Every few hours reviews your open requests listed in a sheet, flags those waiting too long and posts who should pick them up.',
    'category' => 'Support & Success',
    'icon' => 'headphones',
    'color' => 'orange',
    'graph' => $graph([
        $input('spreadsheet_id', 'Open requests Sheet ID'),
        $input('range', 'Range', 'string', true, 'Columns: id, customer, subject, opened_at, owner, priority.'),
        $input('target_hours', 'Target first reply (hours)', 'number'),
        $step('regularly', 'schedule_trigger', ['cron' => '0 */4 * * *']),
        $step('open', 'google_sheets_get_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => $v('range')]),
        $ai('breaches', "You watch a support queue. Using the current time and the opened_at column, list requests that have waited longer than the target, oldest first, with owner and priority, then those within 25% of the target. Suggest who should pick up the unowned ones. If nothing is late reply 'Queue is within target.' {$ONLY_FACTS}", 'Target: '.$v('target_hours')." hours\n\nOpen requests (first row is headers):\n{{ nodes.open }}"),
        $step('post', 'slack_post_message', ['channel' => '#support', 'text' => "*Queue check*\n".$t('breaches')]),
    ], $edges(['regularly', 'open'], ['spreadsheet_id', 'open'], ['range', 'open'], ['open', 'breaches'], ['target_hours', 'breaches'], ['breaches', 'post'])),
];

$templates['content-calendar-drafts'] = [
    'name' => 'Content calendar first drafts',
    'description' => 'Reads this week’s planned topics from your content calendar sheet and saves a first draft of each as a Google Doc for editors.',
    'category' => 'Marketing & Content',
    'icon' => 'file-text',
    'color' => 'teal',
    'graph' => $graph([
        $input('spreadsheet_id', 'Content calendar Sheet ID'),
        $input('range', 'Range', 'string', true, 'Columns: date, topic, audience, angle.'),
        $input('voice', 'Brand voice', 'string', false),
        $step('monday', 'schedule_trigger', ['cron' => '0 8 * * 1']),
        $step('plan', 'google_sheets_get_values', ['spreadsheet_id' => $v('spreadsheet_id'), 'range' => $v('range')]),
        $ai('drafts', "You write first drafts for editors. For each topic planned this week, write a headline, a 3-sentence intro, an outline with H2s and two or three sentences under each, and a call to action, in the brand voice described. Mark any claim needing a source with [source needed]. Separate drafts with a line of dashes. {$ONLY_FACTS}", 'Voice: '.$v('voice')."\n\nCalendar (first row is headers):\n{{ nodes.plan }}"),
        $step('doc', 'google_docs_create_document', ['title' => 'Weekly content drafts', 'content' => $t('drafts')]),
    ], $edges(['monday', 'plan'], ['spreadsheet_id', 'plan'], ['range', 'plan'], ['voice', 'drafts'], ['plan', 'drafts'], ['drafts', 'doc'])),
];

/*
 * Runs that start on a schedule or a webhook have nobody to fill in a run
 * form, so an automatic template keeps its settings (a sheet, a channel, a
 * repository) in one Settings step the user edits once, instead of in Input
 * steps that would stay empty.
 */
foreach ($templates as $slug => $template) {
    $nodes = $template['graph']['nodes'];
    $triggers = array_values(array_filter($nodes, fn (array $node): bool => str_ends_with($node['type'], '_trigger')));
    $inputs = array_values(array_filter($nodes, fn (array $node): bool => $node['type'] === 'workflow_input'));

    if ($triggers === [] || $inputs === []) {
        continue;
    }

    $inputKeys = array_column($inputs, 'key');
    $settings = [];

    foreach ($inputs as $inputNode) {
        $settings[$inputNode['key']] = '<'.($inputNode['config']['label'] ?? $inputNode['key']).'>';
    }

    $rewrite = function (mixed $value) use (&$rewrite, $inputKeys): mixed {
        if (is_array($value)) {
            return array_map($rewrite, $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        foreach ($inputKeys as $key) {
            $value = str_replace('{{ nodes.'.$key.'.value }}', '{{ nodes.settings.'.$key.' }}', $value);
        }

        return $value;
    };

    $kept = [];

    foreach ($nodes as $node) {
        if ($node['type'] === 'workflow_input') {
            continue;
        }

        $node['config'] = $rewrite($node['config']);
        $kept[] = $node;
    }

    $edgeList = [];

    foreach ($template['graph']['edges'] as $edge) {
        $from = in_array($edge['from'], $inputKeys, true) ? 'settings' : $edge['from'];
        $edgeList[$from.'>'.$edge['to'].'>'.($edge['condition'] ?? '')] = ['from' => $from, 'to' => $edge['to'], 'condition' => $edge['condition']];
    }

    foreach ($triggers as $trigger) {
        $edgeList[$trigger['key'].'>settings>'] = ['from' => $trigger['key'], 'to' => 'settings', 'condition' => null];
    }

    $templates[$slug]['graph'] = $graph(
        [...$kept, $step('settings', 'set_variable', ['variables' => $settings])],
        array_values(array_filter($edgeList, fn (array $edge): bool => ! ($edge['from'] === 'settings' && $edge['to'] === 'settings'))),
    );
}

return $templates;
