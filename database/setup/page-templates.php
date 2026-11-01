<?php

use App\Services\Setup\PageBlocks;

/**
 * Global page layouts offered when creating a page for a workflow.
 *
 * Every layout is a finished page: a hero, an explanation of how it works,
 * the form and the result side by side, and the answers to what people ask
 * next. Field and output blocks are unbound slots: `PageSchema::fromTemplate()`
 * fills them with the chosen workflow's inputs and outputs, adding or removing
 * slots to match, so a layout works for a workflow with one input or six.
 * Nothing here makes a claim about a number of customers, results or speed:
 * the copy describes what the page does and leaves proof to the owner.
 *
 * Most layouts are described by `$tool` below; a few are built by hand where
 * the page is not a form-and-result tool.
 */

/**
 * Builds the standard tool page from a plain description of it.
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
$tool = function (array $o): array {
    $align = $o['hero']['align'] ?? 'left';
    $hero = [
        PageBlocks::heading($o['hero']['title'], 1, $align),
        PageBlocks::text($o['hero']['subtitle'], $align),
    ];

    $blocks = [PageBlocks::section($hero, $o['hero']['tone'] ?? 'subtle', 'lg', $align, $o['hero']['image'] ?? null)];

    if (isset($o['highlights'])) {
        $blocks[] = PageBlocks::columns(
            count($o['highlights']) === 2 ? '1-1' : '1-1-1',
            array_map(fn (array $item): array => [PageBlocks::callout($item[0], $item[1], $item[2])], $o['highlights']),
        );
    }

    if (isset($o['steps'])) {
        $blocks[] = PageBlocks::section([
            PageBlocks::heading($o['steps_title'] ?? 'How it works', 2, $align),
            PageBlocks::steps($o['steps']),
        ], 'none', 'sm');
    }

    $form = $o['form'];
    $fieldSlots = array_fill(0, $form['fields'] ?? 2, null);
    $fields = array_map(fn (): array => PageBlocks::field(), $fieldSlots);
    $submit = PageBlocks::submit($form['submit'], $form['running'], $form['submit_align'] ?? 'stretch');
    $outputs = array_map(
        fn (array $output): array => PageBlocks::output($output[0], $output[1], $output[2], $output[3] ?? 'full'),
        $o['outputs'],
    );
    $status = PageBlocks::status($o['idle']);

    $workspace = [PageBlocks::heading($form['title'], 2)];

    if (isset($form['intro'])) {
        $workspace[] = PageBlocks::text($form['intro']);
    }

    if ($o['stacked'] ?? false) {
        array_push($workspace, ...$fields, ...[$submit, PageBlocks::block('divider', ['style' => 'solid']), $status, ...$outputs]);
    } else {
        $workspace[] = PageBlocks::columns(
            $form['layout'] ?? '1-1',
            [[...$fields, $submit], [$status, ...$outputs]],
            'lg',
            'top',
            'subtle',
        );
    }

    if (isset($form['note'])) {
        $workspace[] = PageBlocks::callout($form['note'][0], $form['note'][1], $form['note'][2]);
    }

    $blocks[] = PageBlocks::section($workspace, 'none', 'md');

    foreach ($o['after'] ?? [] as $block) {
        $blocks[] = $block;
    }

    return ['theme' => $o['theme'], 'blocks' => $blocks];
};

$theme = fn (string $accent, array $more = []): array => ['accent' => $accent, 'background' => 'soft', 'radius' => 'lg', 'width' => 'wide', 'font' => 'inter', ...$more];

$templates = [];

/* ----------------------------------------------------------------- Starters */

$templates['simple-form'] = [
    'name' => 'Simple form',
    'description' => 'A clean, focused page: the form, a clear result and a short guide to using it. The right start when you just need it to work.',
    'category' => 'Forms',
    'icon' => 'form',
    'color' => '#4F46E5',
    'content' => ['theme' => $theme('#4f46e5', ['width' => 'medium']), 'blocks' => [
        PageBlocks::heading('Title', 1),
        PageBlocks::text(''),
        PageBlocks::field(),
        PageBlocks::submit('Run', 'Working on it…', 'stretch'),
        PageBlocks::status('Your result will appear here.'),
        PageBlocks::output('Result', 'auto', 'Nothing yet. Fill in the form and press Run.'),
        PageBlocks::block('divider', ['style' => 'dashed']),
        PageBlocks::text('*Something not right? Check the details you entered and run it again.*', 'center'),
    ]],
];

$templates['landing'] = [
    'name' => 'Landing page',
    'description' => 'A centered public page with a gradient hero, three-step explanation, the form and result, and answers to common questions.',
    'category' => 'Public pages',
    'icon' => 'rocket',
    'color' => '#7C3AED',
    'content' => $tool([
        'theme' => $theme('#7c3aed', ['background' => 'gradient', 'radius' => 'xl', 'width' => 'medium']),
        'hero' => ['title' => 'Title', 'subtitle' => 'Tell us what you need and get your result on this page in a moment: no account and no waiting for a reply.', 'tone' => 'accent', 'align' => 'center'],
        'steps' => [['Fill in the form', 'Share the details we ask for. The more specific you are, the better the result.'], ['Press the button', 'We run it straight away and show progress as it works.'], ['Use your result', 'Copy it, keep it or run it again with different details.']],
        'form' => ['title' => 'Get started', 'fields' => 2, 'submit' => 'Get started', 'running' => 'Working on it…', 'submit_align' => 'center'],
        'outputs' => [['Your result', 'auto', 'Your result appears here.']],
        'idle' => 'Your result will appear here.',
        'stacked' => true,
        'after' => [PageBlocks::faq([
            ['Do I need an account?', 'No. Fill in the form on this page and your result appears right here.'],
            ['What happens to the details I enter?', 'They are used to produce your result and handled as described in the owner’s privacy policy.'],
            ['Can I run it again?', 'Yes. Change your details and press the button again as often as you like.'],
        ])],
    ]),
];

$templates['request-form'] = [
    'name' => 'Request form',
    'description' => 'An intake page with a clear explanation of what happens after you submit, a two-column form, and a confirmation you can keep.',
    'category' => 'Forms',
    'icon' => 'clipboard-list',
    'color' => '#0EA5E9',
    'content' => $tool([
        'theme' => $theme('#0ea5e9'),
        'hero' => ['title' => 'Title', 'subtitle' => 'Send us your request and you will see straight away that it arrived and what happens next.', 'tone' => 'subtle'],
        'steps' => [['Submit', 'Describe what you need and any deadline.'], ['We review', 'Your request is checked and routed to the right person.'], ['You hear back', 'You get a reply by the contact details you gave.']],
        'steps_title' => 'What happens after you submit',
        'form' => ['title' => 'Your request', 'fields' => 4, 'layout' => '2-1', 'submit' => 'Send request', 'running' => 'Sending…'],
        'outputs' => [['Confirmation', 'markdown', 'Your confirmation appears here once you submit.']],
        'idle' => 'Submit the form to see your confirmation.',
        'after' => [PageBlocks::faq([
            ['How long does it take?', 'You get a confirmation immediately. How quickly we reply depends on the request, and the confirmation tells you what to expect.'],
            ['Can I change my request?', 'Submit the form again with the corrected details and mention the earlier request.'],
        ])],
    ]),
];

/* --------------------------------------------------------------- Productivity */

$templates['report'] = [
    'name' => 'Report generator',
    'description' => 'A wide, readable page for turning inputs into a finished report, with tabs on how to read it and what it covers.',
    'category' => 'Content',
    'icon' => 'file-text',
    'color' => '#0F766E',
    'content' => $tool([
        'theme' => $theme('#0f766e', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Title', 'subtitle' => 'Enter the details and get a complete, structured report you can read, copy and share.', 'tone' => 'subtle'],
        'form' => ['title' => 'Report details', 'fields' => 3, 'submit' => 'Generate report', 'running' => 'Writing your report…', 'submit_align' => 'left'],
        'outputs' => [['Report', 'markdown', 'Your report appears here.']],
        'idle' => 'Your report will appear here.',
        'stacked' => true,
        'after' => [
            PageBlocks::heading('Reading your report', 2),
            PageBlocks::tabs([
                ['What is covered', "- A short summary up top, so the main point comes first.\n- The supporting detail, grouped under clear headings.\n- Anything that needs a decision or a follow-up."],
                ['Making it better', "- Give specific inputs rather than general ones.\n- Include the period, audience or scope the report should cover.\n- Run it again with a changed input to compare results."],
                ['Sharing it', 'Use the copy button on the report, then paste it into your document, email or chat. Check any figures against your source before you send it on.'],
            ]),
        ],
    ]),
];

$templates['document-summarizer'] = [
    'name' => 'Document summarizer',
    'description' => 'Paste a long document and get a summary and action items side by side, with guidance for getting the best results.',
    'category' => 'Productivity',
    'icon' => 'file-text',
    'color' => '#4F46E5',
    'content' => $tool([
        'theme' => $theme('#4f46e5', ['background' => 'plain', 'radius' => 'md', 'cover_url' => PageBlocks::photo('1454165804606-c3d57bc86b40')]),
        'hero' => ['title' => 'Document summarizer', 'subtitle' => 'Reports, contracts, meeting notes or research: get the gist and what to do next without reading every page.', 'tone' => 'accent'],
        'highlights' => [['info', 'The short version', 'A clear summary in a few paragraphs.'], ['success', 'What to do next', 'Decisions, owners and action items pulled out for you.'], ['warning', 'Nothing invented', 'Only what the document actually says.']],
        'form' => ['title' => 'Paste your document', 'fields' => 1, 'submit' => 'Summarize', 'running' => 'Reading the document…', 'submit_align' => 'left'],
        'outputs' => [['Summary', 'markdown', 'The summary appears here.'], ['Action items', 'markdown', 'Action items appear here.']],
        'idle' => 'Paste your document and press Summarize.',
        'after' => [PageBlocks::section([
            PageBlocks::heading('Tips for better summaries', 2),
            PageBlocks::tabs([
                ['Preparing the text', "- Paste the full text rather than a link, so nothing is missed.\n- Remove headers, footers and page numbers if you can.\n- Keep tables as plain text with one row per line."],
                ['Long documents', "- Summarize one section at a time, then summarize the summaries.\n- Say what you care about (decisions, risks, dates) before pasting."],
                ['Checking the result', 'A summary is a starting point. Open the original for anything you will act on, such as figures, dates or legal wording.'],
            ]),
        ], 'none', 'sm')],
    ]),
];

$templates['meeting-notes'] = [
    'name' => 'Meeting notes to actions',
    'description' => 'Turn a transcript or rough notes into a summary, decisions and an owner-assigned action list the whole team can follow.',
    'category' => 'Productivity',
    'icon' => 'calendar-days',
    'color' => '#2563EB',
    'content' => $tool([
        'theme' => $theme('#2563eb', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Meeting notes to actions', 'subtitle' => 'Paste the transcript or your rough notes. Get a clear summary, the decisions made and who needs to do what by when.', 'tone' => 'subtle'],
        'steps' => [['Paste your notes', 'A transcript, bullet points or messy notes all work.'], ['Get the summary', 'Purpose, decisions and open questions in one place.'], ['Share the actions', 'Copy the action list straight into chat or your task tool.']],
        'form' => ['title' => 'Your meeting', 'fields' => 2, 'layout' => '1-1', 'submit' => 'Summarize meeting', 'running' => 'Reading the notes…'],
        'outputs' => [['Summary and decisions', 'markdown', 'The summary appears here.'], ['Action items', 'table', 'Action items appear here.']],
        'idle' => 'Add your notes and press Summarize meeting.',
        'after' => [PageBlocks::faq([
            ['What if nobody was assigned a task?', 'It is listed as “unassigned” rather than guessed, so you can decide who owns it.'],
            ['Does it add things nobody said?', 'No. It only reports what is in your notes, and marks missing dates or owners as unknown.'],
        ])],
    ]),
];

$templates['translation-hub'] = [
    'name' => 'Translation hub',
    'description' => 'A translation page that keeps tone, formatting and product terms, with notes on what to review before you publish.',
    'category' => 'Productivity',
    'icon' => 'languages',
    'color' => '#E11D48',
    'content' => $tool([
        'theme' => $theme('#e11d48', ['cover_url' => PageBlocks::photo('1523240795612-9a054b0db644'), 'width' => 'medium']),
        'hero' => ['title' => 'Translation hub', 'subtitle' => 'Natural, accurate translations that keep your tone, formatting and product terms intact.', 'tone' => 'subtle', 'align' => 'center'],
        'highlights' => [['info', 'Keeps formatting', 'Lists, headings and links stay in place.'], ['success', 'Sounds native', 'Idiomatic phrasing, not word for word.']],
        'form' => ['title' => 'Translate', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Translate', 'running' => 'Translating…'],
        'outputs' => [['Translation', 'markdown', 'Your translation appears here.']],
        'idle' => 'Your translation will appear here.',
        'after' => [PageBlocks::callout('warning', 'Review before you publish', 'Brand and product names are left untranslated. For legal, medical or safety text, have a qualified reviewer check the result.')],
    ]),
];

$templates['internal-request'] = [
    'name' => 'Internal request desk',
    'description' => 'A no-nonsense internal page for purchase, access and IT requests, with routing information and a clear escalation path.',
    'category' => 'Internal tools',
    'icon' => 'building',
    'color' => '#334155',
    'content' => $tool([
        'theme' => $theme('#334155', ['background' => 'plain', 'radius' => 'sm', 'show_branding' => false, 'cover_url' => PageBlocks::photo('1497366216548-37526070297c')]),
        'hero' => ['title' => 'Request desk', 'subtitle' => 'Submit purchase, access and IT requests in one place. Each request is logged and sent to the team that handles it.', 'tone' => 'dark'],
        'highlights' => [['info', 'Internal use only', 'Do not share this page outside the company.'], ['warning', 'Never include secrets', 'No passwords, keys or card numbers in a request.']],
        'form' => ['title' => 'New request', 'fields' => 4, 'layout' => '2-1', 'submit' => 'Submit request', 'running' => 'Submitting…'],
        'outputs' => [['Request status', 'markdown', 'Your reference and next steps appear here.']],
        'idle' => 'Submit a request to get a reference.',
        'after' => [PageBlocks::section([
            PageBlocks::heading('What happens next', 2),
            PageBlocks::text("1. Your request is logged and gets a reference.\n2. It is routed to the owning team, and approvals are requested where needed.\n3. You are updated when it is approved, in progress or complete.\n\nIf it is urgent or blocks your work, say so in the request and tell your manager."),
        ], 'subtle', 'md')],
    ]),
];

/* -------------------------------------------------------------------- Sales */

$templates['lead-qualification'] = [
    'name' => 'Lead capture and qualification',
    'description' => 'A sales page that collects a prospect’s details, scores the fit and recommends a next step, with reasons to choose you and FAQs.',
    'category' => 'Sales',
    'icon' => 'target',
    'color' => '#2563EB',
    'content' => $tool([
        'theme' => $theme('#2563eb', ['cover_url' => PageBlocks::photo('1556761175-5973dc0f32e7'), 'width' => 'medium']),
        'hero' => ['title' => 'Get your free assessment', 'subtitle' => 'Tell us a little about your business and see how well we fit, and the best next step for you, on this page straight away.', 'tone' => 'accent', 'align' => 'center'],
        'highlights' => [['success', 'Free', 'No payment details needed.'], ['info', 'Instant', 'Your result appears on this page.'], ['info', 'No commitment', 'Nothing is booked unless you ask.']],
        'form' => ['title' => 'About you', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Get my assessment', 'running' => 'Assessing…'],
        'outputs' => [['Your fit', 'auto', 'Your fit score appears here.'], ['Recommended next step', 'markdown', 'Your next step appears here.']],
        'idle' => 'Your assessment will appear here.',
        'after' => [
            PageBlocks::heading('Why teams choose us', 2, 'center'),
            PageBlocks::columns('1-1-1', [
                [PageBlocks::callout('info', 'A guided start', 'We help you set up properly rather than leaving you to work it out.')],
                [PageBlocks::callout('info', 'A real person', 'Questions go to someone who knows your account.')],
                [PageBlocks::callout('info', 'Clear terms', 'You see what is included before you decide.')],
            ]),
            PageBlocks::faq([
                ['What happens after I submit?', 'You see your score and a recommended next step right away. If it looks like a good fit, our team may get in touch.'],
                ['Will you share my details?', 'No. They are used to prepare your assessment and follow up with you.'],
                ['Is the assessment a quote?', 'No. It is an indication of fit. Pricing is confirmed after we understand your needs.'],
            ]),
        ],
    ]),
];

$templates['sales-call-prep'] = [
    'name' => 'Sales call prep',
    'description' => 'An internal page for reps: enter a prospect and get a one-page brief with likely pains, discovery questions and objections.',
    'category' => 'Sales',
    'icon' => 'briefcase',
    'color' => '#7C3AED',
    'content' => $tool([
        'theme' => $theme('#7c3aed', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Sales call prep', 'subtitle' => 'Enter who you are meeting and what you know. Walk into the call with a brief, the right questions and answers to likely objections.', 'tone' => 'subtle'],
        'highlights' => [['info', 'Discovery questions', 'Ordered from broad to specific.'], ['info', 'Likely objections', 'With an honest response to each.'], ['warning', 'Assumptions are labelled', 'Verify them on the call.']],
        'form' => ['title' => 'Your prospect', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Build my brief', 'running' => 'Preparing…', 'submit_align' => 'left'],
        'outputs' => [['Call brief', 'markdown', 'Your brief appears here.']],
        'idle' => 'Enter your prospect to get a brief.',
        'after' => [PageBlocks::callout('info', 'Tip', 'Paste the prospect’s website copy or LinkedIn summary into the notes field: the brief is only as specific as what you give it.')],
    ]),
];

$templates['proposal-generator'] = [
    'name' => 'Proposal generator',
    'description' => 'Turn discovery notes into a structured client proposal with scope, deliverables, timeline and next steps.',
    'category' => 'Sales',
    'icon' => 'file-text',
    'color' => '#0F766E',
    'content' => $tool([
        'theme' => $theme('#0f766e', ['background' => 'plain']),
        'hero' => ['title' => 'Proposal generator', 'subtitle' => 'Give us the client, their goals and what you offer. Get a clear proposal draft you can edit and send.', 'tone' => 'accent'],
        'steps' => [['Describe the client', 'Their goal, constraints and budget range.'], ['Describe your offer', 'Scope, deliverables and how you work.'], ['Edit and send', 'Review the draft, add pricing and send it.']],
        'form' => ['title' => 'Proposal details', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Draft the proposal', 'running' => 'Drafting…', 'submit_align' => 'left'],
        'outputs' => [['Proposal draft', 'markdown', 'Your draft appears here.']],
        'idle' => 'Your proposal draft will appear here.',
        'after' => [PageBlocks::callout('warning', 'Always review before sending', 'The draft uses only what you provide. Check scope, dates and pricing, and add legal terms from your own agreement.')],
    ]),
];

/* ---------------------------------------------------------------- Support */

$templates['support-assistant'] = [
    'name' => 'Customer support assistant',
    'description' => 'A help-center page with popular topics, a question form, a formatted answer and a clear path to a person.',
    'category' => 'Support',
    'icon' => 'headphones',
    'color' => '#0D9488',
    'content' => $tool([
        'theme' => $theme('#0d9488', ['background' => 'plain', 'radius' => 'md', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1521737604893-d14cc237f11d')]),
        'hero' => ['title' => 'How can we help?', 'subtitle' => 'Ask about your account, billing or the product and get an answer right away.', 'tone' => 'accent', 'align' => 'center'],
        'highlights' => [['info', 'Account and sign-in', 'Passwords, email changes and access.'], ['info', 'Billing', 'Invoices, payment methods and plans.'], ['info', 'Using the product', 'Getting set up and getting unstuck.']],
        'form' => ['title' => 'Ask a question', 'fields' => 2, 'layout' => '1-1', 'submit' => 'Get an answer', 'running' => 'Finding the answer…'],
        'outputs' => [['Answer', 'markdown', 'Ask a question to see the answer.']],
        'idle' => 'Your answer will show up here.',
        'form_note' => null,
        'after' => [
            PageBlocks::callout('warning', 'Still stuck?', 'If the answer does not solve it, reply to any of our emails or contact support: a person will pick it up.'),
            PageBlocks::faq([
                ['What should I include in my question?', 'What you were trying to do, what happened instead, and any error message exactly as you saw it.'],
                ['Is it safe to share account details?', 'Never share passwords or full card numbers. A name or order number is enough for us to help.'],
            ]),
        ],
    ]),
];

$templates['feedback-survey'] = [
    'name' => 'Feedback survey',
    'description' => 'A short, friendly survey page that thanks people, explains what you do with answers and shows a personal reply.',
    'category' => 'Support',
    'icon' => 'message-square',
    'color' => '#16A34A',
    'content' => $tool([
        'theme' => $theme('#16a34a', ['width' => 'narrow', 'cover_url' => PageBlocks::photo('1531482615713-2afd69097998')]),
        'hero' => ['title' => 'Tell us how we are doing', 'subtitle' => 'Your feedback decides what we improve next. It takes a couple of minutes, and you can skip anything you would rather not answer.', 'tone' => 'subtle', 'align' => 'center'],
        'form' => ['title' => 'Your feedback', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Send feedback', 'running' => 'Sending…'],
        'stacked' => true,
        'outputs' => [['Our reply', 'markdown', 'Thank you. Your reply appears here once you submit.']],
        'idle' => 'Your thank-you will appear here.',
        'after' => [PageBlocks::text('*We read every response and share what we changed in our updates.*', 'center')],
    ]),
];

$templates['return-request'] = [
    'name' => 'Return and refund request',
    'description' => 'A self-service page for returns and refunds: customers state the problem and see the policy-based outcome and next step.',
    'category' => 'Support',
    'icon' => 'package',
    'color' => '#EA580C',
    'content' => $tool([
        'theme' => $theme('#ea580c', ['width' => 'medium']),
        'hero' => ['title' => 'Returns and refunds', 'subtitle' => 'Tell us what went wrong with your order and we will tell you exactly what happens next.', 'tone' => 'subtle', 'align' => 'center'],
        'steps' => [['Tell us about the order', 'Your order number and what went wrong.'], ['See your options', 'We apply our returns policy straight away.'], ['Follow the next step', 'A prepaid label, a replacement or a refund, as the policy allows.']],
        'form' => ['title' => 'Your order', 'fields' => 4, 'layout' => '1-1', 'submit' => 'See my options', 'running' => 'Checking your order…'],
        'outputs' => [['What we can do', 'markdown', 'Your options appear here.']],
        'idle' => 'Your options will appear here.',
        'after' => [
            PageBlocks::faq([
                ['How long do refunds take?', 'Once a refund is issued it can take several business days to appear, depending on your bank or card.'],
                ['What if my item arrived damaged?', 'Include a clear description, and a photo if you can. It helps us resolve it faster.'],
                ['Need a person?', 'Reply to your order confirmation email and our team will pick it up.'],
            ]),
        ],
    ]),
];

/* ----------------------------------------------------------------- Marketing */

$templates['content-studio'] = [
    'name' => 'AI content studio',
    'description' => 'A creative page for turning a brief into a first draft, with tabs of tips for tone, structure and editing.',
    'category' => 'Content',
    'icon' => 'sparkles',
    'color' => '#7C3AED',
    'content' => $tool([
        'theme' => $theme('#7c3aed', ['background' => 'gradient', 'radius' => 'xl', 'cover_url' => PageBlocks::photo('1499750310107-5fef28a66643')]),
        'hero' => ['title' => 'AI content studio', 'subtitle' => 'Describe what you need to write and get a first draft in your voice. Spend your time editing, not staring at a blank page.', 'tone' => 'accent', 'align' => 'center'],
        'steps' => [['Write your brief', 'Topic, audience and the tone you want.'], ['Generate a draft', 'A complete first version in moments.'], ['Make it yours', 'Edit, add your own examples and publish.']],
        'form' => ['title' => 'Your brief', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Write my draft', 'running' => 'Writing…'],
        'outputs' => [['Your draft', 'markdown', 'Your draft appears here.']],
        'idle' => 'Fill in the brief and press the button.',
        'after' => [
            PageBlocks::heading('Getting a better draft', 2),
            PageBlocks::tabs([
                ['Be specific', 'Name the reader, the one thing they should take away and the action you want them to take.'],
                ['Set the voice', 'Describe the tone in a phrase (“friendly and direct, no jargon”) and paste a short example of writing you like.'],
                ['Edit with care', 'Add your own examples and numbers, and check every claim before you publish.'],
            ]),
        ],
    ]),
];

$templates['campaign-brief'] = [
    'name' => 'Campaign brief builder',
    'description' => 'Turn a goal, audience and budget into a structured campaign brief with channels, timeline and KPIs a team can run with.',
    'category' => 'Marketing',
    'icon' => 'megaphone',
    'color' => '#DB2777',
    'content' => $tool([
        'theme' => $theme('#db2777', ['background' => 'gradient', 'radius' => 'xl']),
        'hero' => ['title' => 'Campaign brief builder', 'subtitle' => 'Describe the goal, audience and budget. Get a brief you can hand straight to your team.', 'tone' => 'accent'],
        'steps' => [['State the goal', 'Make it measurable, such as qualified leads by a date.'], ['Add audience and budget', 'Who you are reaching and what you can spend.'], ['Get the brief', 'Objective, channels, plan, KPIs and risks.']],
        'form' => ['title' => 'Campaign details', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Build the brief', 'running' => 'Drafting the brief…', 'submit_align' => 'left'],
        'outputs' => [['Campaign brief', 'markdown', 'Your brief appears here.']],
        'idle' => 'Your brief will appear here.',
        'after' => [PageBlocks::callout('info', 'Tip', 'Give a measurable goal, such as 200 qualified leads in 6 weeks, and the brief will be much sharper.')],
    ]),
];

$templates['seo-content-brief'] = [
    'name' => 'SEO content brief',
    'description' => 'Enter a keyword and audience; get search intent, an outline, questions to answer and a call to action in one brief.',
    'category' => 'Marketing',
    'icon' => 'search',
    'color' => '#0284C7',
    'content' => $tool([
        'theme' => $theme('#0284c7', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'SEO content brief', 'subtitle' => 'Plan an article that answers what people are really searching for, before anyone writes a word.', 'tone' => 'subtle'],
        'highlights' => [['info', 'Search intent', 'What the reader actually wants.'], ['info', 'Outline', 'H2 and H3 structure ready to write.'], ['warning', 'No made-up volumes', 'Data you did not supply is never invented.']],
        'form' => ['title' => 'Your topic', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Create brief', 'running' => 'Researching the angle…', 'submit_align' => 'left'],
        'outputs' => [['Content brief', 'markdown', 'Your brief appears here.']],
        'idle' => 'Enter a keyword to get your brief.',
        'after' => [PageBlocks::faq([
            ['Does it check live search results?', 'No. It works from the keyword and details you give it. Verify rankings and volumes in your SEO tool.'],
            ['What do I do with the brief?', 'Hand it to a writer, or use the outline to draft the article yourself.'],
        ])],
    ]),
];

$templates['website-audit'] = [
    'name' => 'Website audit',
    'description' => 'A lead-generation page that audits a site for speed, SEO and content, and shows what to fix first.',
    'category' => 'Marketing',
    'icon' => 'globe',
    'color' => '#0284C7',
    'content' => $tool([
        'theme' => $theme('#0284c7', ['background' => 'plain', 'radius' => 'md', 'cover_url' => PageBlocks::photo('1460925895917-afdab827c52f')]),
        'hero' => ['title' => 'Free website audit', 'subtitle' => 'Find out what is holding your site back in search, and exactly what to fix first.', 'tone' => 'accent'],
        'highlights' => [['info', 'Speed', 'Load time, image sizes and blocking scripts.'], ['info', 'On-page SEO', 'Titles, descriptions, headings and links.'], ['info', 'Content', 'Readability, keywords and thin pages.']],
        'form' => ['title' => 'Audit your site', 'fields' => 2, 'layout' => '1-1', 'submit' => 'Run audit', 'running' => 'Auditing…'],
        'outputs' => [['Audit summary', 'markdown', 'Your audit appears here.', 'full'], ['Fix first', 'markdown', 'Your priorities appear here.', 'full']],
        'idle' => 'Enter your address to start the audit.',
        'after' => [PageBlocks::faq([
            ['Is the audit free?', 'Yes. Run it as many times as you like.'],
            ['Will you contact me?', 'Only if you ask us to. The audit shows on this page and nothing else happens.'],
            ['How accurate is it?', 'It highlights likely issues quickly. Confirm anything important with your own tools.'],
        ])],
    ]),
];

$templates['event-registration'] = [
    'name' => 'Event registration',
    'description' => 'An event page with the key details, agenda tabs, a sign-up form, a confirmation and FAQs.',
    'category' => 'Events',
    'icon' => 'calendar',
    'color' => '#DB2777',
    'content' => ['theme' => $theme('#db2777', ['background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1540575467063-178a50c2df87')]), 'blocks' => [
        PageBlocks::section([
            PageBlocks::heading('Event name', 1, 'center'),
            PageBlocks::text('Say what the event is, who it is for and why they should come. Update the details below before you publish.', 'center'),
        ], 'accent', 'lg', 'center'),
        PageBlocks::columns('1-1-1', [
            [PageBlocks::callout('info', 'When', 'Add the date and time, including the time zone.')],
            [PageBlocks::callout('info', 'Where', 'Add the venue or the link people will join with.')],
            [PageBlocks::callout('success', 'Price', 'Say whether it is free or what a ticket costs.')],
        ]),
        PageBlocks::heading('What to expect', 2),
        PageBlocks::tabs([
            ['Agenda', "Add your schedule here, for example:\n\n- **Doors open**\n- **Opening talk**\n- **Workshops**\n- **Networking**"],
            ['Speakers', 'Introduce the people on stage and what they will cover.'],
            ['Venue and travel', 'Add directions, parking, accessibility information and the nearest transport.'],
        ]),
        PageBlocks::section([
            PageBlocks::heading('Reserve your spot', 2),
            PageBlocks::columns('1-1', [
                [PageBlocks::field(), PageBlocks::field(), PageBlocks::submit('Register', 'Reserving your seat…', 'stretch')],
                [PageBlocks::status('Your confirmation will appear here.'), PageBlocks::output('Your confirmation', 'markdown', 'Register to see your confirmation.')],
            ], 'lg', 'top', 'subtle'),
        ]),
        PageBlocks::faq([
            ['Can I bring a colleague?', 'Yes. Each person registers separately so we can plan numbers.'],
            ['Can I cancel?', 'Tell us as early as you can so someone else can take your seat.'],
            ['Is there accessible access?', 'Add the accessibility details for your venue here.'],
        ]),
    ]],
];

/* ----------------------------------------------------------------- Operations */

$templates['data-extractor'] = [
    'name' => 'Data extractor',
    'description' => 'Turns messy text, emails or invoices into a clean table plus the raw structured result.',
    'category' => 'Operations',
    'icon' => 'table',
    'color' => '#EA580C',
    'content' => $tool([
        'theme' => $theme('#ea580c', ['background' => 'dots', 'radius' => 'md', 'cover_url' => PageBlocks::photo('1551288049-bebda4e38f71')]),
        'hero' => ['title' => 'Data extractor', 'subtitle' => 'Paste an invoice, an email thread or any messy text and get clean, structured data back, ready for a spreadsheet or your CRM.', 'tone' => 'subtle'],
        'highlights' => [['info', 'Invoices and receipts', 'Vendor, dates, line items and totals.'], ['info', 'Emails and tickets', 'Names, companies, requests and deadlines.'], ['info', 'Forms and notes', 'Any fields you would otherwise copy by hand.']],
        'form' => ['title' => 'Paste your text', 'fields' => 2, 'layout' => '1-2', 'submit' => 'Extract data', 'running' => 'Extracting…'],
        'outputs' => [['Extracted data', 'table', 'Your table appears here.'], ['Raw result', 'json', 'The structured result appears here.']],
        'idle' => 'Results will appear here.',
        'after' => [PageBlocks::callout('warning', 'Handle with care', 'Remove passwords, full card numbers and other secrets before pasting. Check extracted figures against the source before you rely on them.')],
    ]),
];

$templates['developer-tool'] = [
    'name' => 'Developer tool',
    'description' => 'A dark, monospaced and compact page for internal tools and API-style workflows, with copyable raw output.',
    'category' => 'Internal tools',
    'icon' => 'terminal',
    'color' => '#0F172A',
    'content' => ['theme' => $theme('#38bdf8', ['appearance' => 'dark', 'background' => 'plain', 'radius' => 'sm', 'font' => 'jetbrains_mono', 'width' => 'medium']), 'blocks' => [
        PageBlocks::heading('Title', 1),
        PageBlocks::text(''),
        PageBlocks::columns('1-1', [
            [PageBlocks::heading('Input', 3), PageBlocks::field(), PageBlocks::submit('Run', 'Running…', 'stretch')],
            [PageBlocks::heading('Output', 3), PageBlocks::status('Waiting for input.'), PageBlocks::output('Response', 'json', 'No response yet.')],
        ], 'lg'),
        PageBlocks::block('divider', ['style' => 'dashed']),
        PageBlocks::text('Output is copyable. Inputs are passed to the workflow exactly as entered.'),
    ]],
];

$templates['image-generator'] = [
    'name' => 'Image studio',
    'description' => 'A dark, visual page for generating images from a description, with prompt ideas and what makes a good prompt.',
    'category' => 'Design',
    'icon' => 'image',
    'color' => '#A855F7',
    'content' => $tool([
        'theme' => $theme('#a855f7', ['appearance' => 'dark', 'background' => 'gradient', 'radius' => 'xl', 'width' => 'medium', 'cover_url' => PageBlocks::photo('1557683316-973673baf926')]),
        'hero' => ['title' => 'Image studio', 'subtitle' => 'Describe what you imagine and get an original image.', 'tone' => 'none', 'align' => 'center'],
        'highlights' => [['info', 'Be specific', 'Subject, setting and mood.'], ['info', 'Pick a style', 'Photo, illustration, 3D, watercolor.'], ['info', 'Set the light', 'Golden hour, studio, neon.']],
        'form' => ['title' => 'Describe your image', 'fields' => 2, 'layout' => '1-1', 'submit' => 'Create image', 'running' => 'Creating…'],
        'outputs' => [['Your image', 'image', 'Your image appears here.']],
        'idle' => 'Describe an image and press Create image.',
        'after' => [PageBlocks::heading('Prompt ideas', 2), PageBlocks::tabs([
            ['Product', 'A matte ceramic mug on a pale oak table, soft morning window light, shallow depth of field, photographed from slightly above.'],
            ['Illustration', 'A flat vector illustration of a lighthouse at dusk, warm window glow, limited palette of navy, coral and cream.'],
            ['Scene', 'A quiet market street after rain, reflections on cobblestones, lanterns lit, cinematic wide shot.'],
        ])],
    ]),
];

/* ---------------------------------------------------------------------- HR */

$templates['candidate-screening'] = [
    'name' => 'Candidate screening',
    'description' => 'A recruiting page where a hiring team enters a job and a resume and gets an evidence-based score and interview focus areas.',
    'category' => 'HR & Recruiting',
    'icon' => 'users',
    'color' => '#2563EB',
    'content' => $tool([
        'theme' => $theme('#2563eb', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Candidate screening', 'subtitle' => 'Enter the job requirements and a resume to see how well they match, the evidence for each score and what to verify in the interview.', 'tone' => 'subtle'],
        'highlights' => [['success', 'Evidence for every score', 'Each point quotes the resume or says it was not found.'], ['info', 'Interview focus areas', 'Gaps and claims worth probing.'], ['warning', 'A person decides', 'Scores support your judgement. They look only at skills and experience, never protected characteristics.']],
        'form' => ['title' => 'Role and resume', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Screen candidate', 'running' => 'Reading the resume…', 'submit_align' => 'left'],
        'outputs' => [['Match and evidence', 'markdown', 'The score and evidence appear here.']],
        'idle' => 'Enter a role and a resume to see the match.',
        'after' => [PageBlocks::section([
            PageBlocks::heading('Good practice', 2),
            PageBlocks::tabs([
                ['Fair screening', "- Screen every candidate against the same written requirements.\n- Keep must-haves short; long wish lists hide strong candidates.\n- Remove names and photos from resumes before screening if you can."],
                ['After the score', 'Use the interview focus areas as a starting point, not a verdict. Record your own notes and reasons for every decision.'],
            ]),
        ], 'none', 'sm')],
    ]),
];

$templates['job-application'] = [
    'name' => 'Job application',
    'description' => 'A careers page: a welcoming introduction, what applying involves, the application form and a confirmation.',
    'category' => 'HR & Recruiting',
    'icon' => 'briefcase',
    'color' => '#7C3AED',
    'content' => $tool([
        'theme' => $theme('#7c3aed', ['width' => 'medium']),
        'hero' => ['title' => 'Apply for this role', 'subtitle' => 'Tell us about yourself. We read every application and reply to everyone.', 'tone' => 'accent', 'align' => 'center'],
        'steps' => [['Apply', 'Fill in the form. It takes a few minutes.'], ['We review', 'The team reads your application against the role.'], ['We reply', 'You hear from us whether or not you move forward.']],
        'steps_title' => 'What happens next',
        'form' => ['title' => 'Your application', 'fields' => 5, 'layout' => '2-1', 'submit' => 'Submit application', 'running' => 'Sending…'],
        'outputs' => [['Confirmation', 'markdown', 'Your confirmation appears here after you apply.']],
        'idle' => 'Submit your application to get a confirmation.',
        'after' => [PageBlocks::faq([
            ['Can I apply for more than one role?', 'Yes. Submit a separate application for each role.'],
            ['Do you need a resume?', 'Add your resume or a link to your work if the form asks for one. If you have none, tell us about your experience in your own words.'],
            ['How do you handle my data?', 'It is used only to consider your application. Ask us at any time to delete it.'],
            ['Do you need any adjustments?', 'Tell us. We will adapt the process so you can show your best.'],
        ])],
    ]),
];

/* ---------------------------------------------------------------- Finance */

$templates['expense-submission'] = [
    'name' => 'Expense submission',
    'description' => 'A finance intake page where employees submit expenses and see right away whether they meet policy and what is missing.',
    'category' => 'Finance',
    'icon' => 'receipt',
    'color' => '#059669',
    'content' => $tool([
        'theme' => $theme('#059669', ['background' => 'plain', 'radius' => 'md', 'width' => 'medium']),
        'hero' => ['title' => 'Submit an expense', 'subtitle' => 'Enter your expense and get an instant check against company policy before it goes to finance.', 'tone' => 'subtle'],
        'highlights' => [['info', 'Keep your receipt', 'Most claims above a small amount need one.'], ['info', 'Say why', 'The business purpose speeds up approval.']],
        'form' => ['title' => 'Your expense', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Check my expense', 'running' => 'Checking…'],
        'outputs' => [['Policy check', 'markdown', 'Your result appears here.']],
        'idle' => 'The policy check will appear here.',
        'after' => [PageBlocks::section([PageBlocks::heading('Common reasons for delays', 2), PageBlocks::text("- A missing or unreadable receipt.\n- An amount above the category limit without prior approval.\n- A description that does not state the business purpose.")], 'subtle', 'md')],
    ]),
];

$templates['invoice-intake'] = [
    'name' => 'Invoice intake',
    'description' => 'Paste an invoice and get the vendor, dates, line items and totals as a table, with the arithmetic checked.',
    'category' => 'Finance',
    'icon' => 'receipt',
    'color' => '#0F766E',
    'content' => $tool([
        'theme' => $theme('#0f766e', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Invoice intake', 'subtitle' => 'Turn an invoice into clean data and catch mistakes before it is paid.', 'tone' => 'subtle'],
        'steps' => [['Paste the invoice', 'Copy the text from the PDF or email.'], ['We read it', 'Vendor, dates, line items, tax and total.'], ['We check the maths', 'Totals are recalculated and mismatches flagged.']],
        'form' => ['title' => 'Invoice', 'fields' => 2, 'layout' => '1-2', 'submit' => 'Read invoice', 'running' => 'Reading the invoice…'],
        'outputs' => [['Invoice details', 'table', 'Details appear here.'], ['Raw data', 'json', 'The structured result appears here.']],
        'idle' => 'Paste an invoice to begin.',
        'after' => [PageBlocks::callout('warning', 'Check before you pay', 'Compare the extracted figures with the original, and confirm the payee details independently before you pay a new vendor.')],
    ]),
];

/* -------------------------------------------------------------- E-commerce */

$templates['order-help'] = [
    'name' => 'Order help',
    'description' => 'A store help page for late, damaged or wrong-item orders that gives customers a clear next step and sets expectations.',
    'category' => 'E-commerce',
    'icon' => 'package',
    'color' => '#EA580C',
    'content' => $tool([
        'theme' => $theme('#ea580c', ['width' => 'medium']),
        'hero' => ['title' => 'Help with your order', 'subtitle' => 'Tell us what went wrong and we will tell you exactly what happens next.', 'tone' => 'subtle', 'align' => 'center'],
        'highlights' => [['info', 'Have these ready', 'Your order number and the email used at checkout.'], ['info', 'Damaged item?', 'A photo helps us resolve it faster.']],
        'form' => ['title' => 'Your order', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Get help', 'running' => 'Looking at your order…'],
        'outputs' => [['What we can do', 'markdown', 'Your options appear here.']],
        'idle' => 'Your answer will appear here.',
        'after' => [PageBlocks::callout('warning', 'Need a person?', 'If this does not solve it, reply to your order confirmation email and our team will pick it up.')],
    ]),
];

$templates['product-description-writer'] = [
    'name' => 'Product description writer',
    'description' => 'Turn product facts into a benefit-led title, description and SEO fields that you can paste into your store.',
    'category' => 'E-commerce',
    'icon' => 'shopping-cart',
    'color' => '#2563EB',
    'content' => $tool([
        'theme' => $theme('#2563eb', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Product description writer', 'subtitle' => 'Enter the facts. Get a title, a description and search fields written around what the customer gets.', 'tone' => 'accent'],
        'highlights' => [['info', 'Benefits first', 'Features are tied to what they do for the buyer.'], ['info', 'SEO fields', 'A search title and a meta description.'], ['warning', 'Only your facts', 'No invented materials, claims or certifications.']],
        'form' => ['title' => 'Product facts', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Write description', 'running' => 'Writing…', 'submit_align' => 'left'],
        'outputs' => [['Product copy', 'markdown', 'Your copy appears here.']],
        'idle' => 'Add your product facts to get copy.',
        'after' => [PageBlocks::faq([
            ['What should I include?', 'Materials, dimensions, features, who it is for and anything that sets it apart. The more concrete, the better.'],
            ['Can I use it as is?', 'Treat it as a strong first draft. Check every claim against the product before publishing.'],
        ])],
    ]),
];

/* -------------------------------------------------------------- Engineering */

$templates['bug-report-intake'] = [
    'name' => 'Bug report intake',
    'description' => 'A product page that turns a rough bug report into a clean, reproducible report with severity, ready for engineering.',
    'category' => 'Engineering',
    'icon' => 'bug',
    'color' => '#DC2626',
    'content' => $tool([
        'theme' => $theme('#dc2626', ['background' => 'plain', 'radius' => 'md', 'width' => 'medium']),
        'hero' => ['title' => 'Report a problem', 'subtitle' => 'Describe what happened in your own words. We turn it into a clear report with steps to reproduce.', 'tone' => 'subtle'],
        'highlights' => [['warning', 'Keep it safe', 'Do not include passwords, tokens or personal data.'], ['info', 'Be exact', 'Quote any error message exactly.']],
        'form' => ['title' => 'What happened?', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Create report', 'running' => 'Writing the report…', 'submit_align' => 'left'],
        'outputs' => [['Bug report', 'markdown', 'The structured report appears here.']],
        'idle' => 'Your report will appear here.',
        'after' => [PageBlocks::section([PageBlocks::heading('What makes a great report', 2), PageBlocks::text("- What you expected to happen and what actually happened.\n- The exact error message, in quotes.\n- Your browser or device, and when it happened.\n- Whether it happens every time.")], 'subtle', 'md')],
    ]),
];

$templates['code-review-helper'] = [
    'name' => 'Code review helper',
    'description' => 'Paste a diff and get a review ranked by severity: bugs and security first, then performance, tests and readability.',
    'category' => 'Engineering',
    'icon' => 'code',
    'color' => '#0F172A',
    'content' => $tool([
        'theme' => $theme('#6366f1', ['appearance' => 'dark', 'background' => 'plain', 'radius' => 'sm', 'font' => 'jetbrains_mono']),
        'hero' => ['title' => 'Code review helper', 'subtitle' => 'A second pair of eyes on a change before a human reviews it. It looks for bugs and security issues first.', 'tone' => 'none'],
        'form' => ['title' => 'Your change', 'fields' => 2, 'layout' => '1-1', 'submit' => 'Review code', 'running' => 'Reviewing…', 'submit_align' => 'left'],
        'outputs' => [['Review', 'markdown', 'Findings appear here.']],
        'idle' => 'Paste a diff to start.',
        'after' => [PageBlocks::callout('warning', 'Do not paste secrets', 'Remove keys, tokens and credentials from the diff first. This review supports a human reviewer and does not replace one.')],
    ]),
];

/* ------------------------------------------------------ Legal & Real estate */

$templates['contract-review-request'] = [
    'name' => 'Contract review',
    'description' => 'A legal intake page where teams paste a contract and get a plain-language summary, flagged clauses and negotiation points.',
    'category' => 'Legal',
    'icon' => 'scale',
    'color' => '#4338CA',
    'content' => $tool([
        'theme' => $theme('#4338ca', ['background' => 'plain', 'radius' => 'md']),
        'hero' => ['title' => 'Contract review', 'subtitle' => 'Paste a contract to get its key terms, unusual clauses and suggested edits before it goes to legal.', 'tone' => 'subtle'],
        'highlights' => [['info', 'Key terms', 'Parties, term, payment, renewal and termination.'], ['info', 'Flagged clauses', 'One-sided or unusual wording, quoted.'], ['warning', 'Not legal advice', 'A qualified lawyer should approve anything significant.']],
        'form' => ['title' => 'Your contract', 'fields' => 2, 'layout' => '1-2', 'submit' => 'Review contract', 'running' => 'Reading the contract…', 'submit_align' => 'left'],
        'outputs' => [['Key terms', 'markdown', 'Key terms appear here.'], ['Risks and suggested edits', 'markdown', 'Flagged clauses appear here.']],
        'idle' => 'The review will appear here.',
        'after' => [PageBlocks::faq([
            ['Is my contract stored?', 'It is processed to produce the review. See the owner’s privacy policy for how data is handled.'],
            ['Can I rely on the result?', 'Use it to prepare questions for your lawyer. It does not replace professional advice.'],
        ])],
    ]),
];

$templates['property-enquiry'] = [
    'name' => 'Property enquiry',
    'description' => 'A real-estate page that captures a buyer’s or seller’s details and needs and replies with a tailored next step.',
    'category' => 'Real estate',
    'icon' => 'home',
    'color' => '#0F766E',
    'content' => $tool([
        'theme' => $theme('#0f766e', ['background' => 'soft', 'radius' => 'xl', 'width' => 'medium']),
        'hero' => ['title' => 'Find your next move', 'subtitle' => 'Tell us what you are looking for, or what you would like to sell, and an agent will follow up with the right next step.', 'tone' => 'accent', 'align' => 'center'],
        'highlights' => [['info', 'A quick reply', 'You see a suggested next step on this page straight away.'], ['info', 'No obligation', 'Asking a question never commits you.'], ['info', 'Private', 'Your details are used only to answer your enquiry.']],
        'form' => ['title' => 'Your enquiry', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Send enquiry', 'running' => 'Sending…', 'submit_align' => 'center'],
        'outputs' => [['Your next step', 'markdown', 'A tailored next step appears here.']],
        'idle' => 'Your reply will appear here.',
        'after' => [PageBlocks::faq([
            ['Do you charge to enquire?', 'No. Enquiries are free.'],
            ['How soon will an agent be in touch?', 'You get a suggested next step now; an agent follows up using the contact details you give.'],
        ])],
    ]),
];

/* ----------------------------------------------------- Education & Healthcare */

$templates['lesson-planner'] = [
    'name' => 'Lesson planner',
    'description' => 'A teacher page that builds a timed lesson plan with objectives, differentiation and an exit assessment from a topic and level.',
    'category' => 'Education',
    'icon' => 'graduation-cap',
    'color' => '#7C3AED',
    'content' => $tool([
        'theme' => $theme('#7c3aed', ['width' => 'medium']),
        'hero' => ['title' => 'Lesson planner', 'subtitle' => 'Enter the topic, level and the time you have to teach. Get a ready-to-teach plan with timings, activities and a quick check for understanding.', 'tone' => 'accent'],
        'highlights' => [['info', 'Objectives', 'Measurable goals for the lesson.'], ['info', 'Differentiation', 'Support and extension built in.'], ['info', 'Exit assessment', 'A short check before students leave.']],
        'form' => ['title' => 'Your lesson', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Plan my lesson', 'running' => 'Planning…', 'submit_align' => 'left'],
        'outputs' => [['Lesson plan', 'markdown', 'Your plan appears here.']],
        'idle' => 'Your lesson plan will appear here.',
        'after' => [PageBlocks::callout('info', 'Make it yours', 'Review every plan before teaching and adjust it to your class, your resources and your curriculum.')],
    ]),
];

$templates['quiz-maker'] = [
    'name' => 'Quiz maker',
    'description' => 'Paste course material and get a quiz with an answer key, explanations and difficulty tags.',
    'category' => 'Education',
    'icon' => 'clipboard-list',
    'color' => '#16A34A',
    'content' => $tool([
        'theme' => $theme('#16a34a', ['background' => 'plain', 'radius' => 'md', 'width' => 'medium']),
        'hero' => ['title' => 'Quiz maker', 'subtitle' => 'Turn your notes or reading into a quiz that tests what is actually in the material.', 'tone' => 'subtle'],
        'steps' => [['Paste the material', 'Notes, a chapter or an article.'], ['Choose the length', 'How many questions you want.'], ['Get the quiz', 'Questions, an answer key and explanations.']],
        'form' => ['title' => 'Your material', 'fields' => 3, 'layout' => '1-1', 'submit' => 'Make quiz', 'running' => 'Writing questions…', 'submit_align' => 'left'],
        'outputs' => [['Quiz', 'markdown', 'Your quiz appears here.']],
        'idle' => 'Your quiz will appear here.',
        'after' => [PageBlocks::faq([
            ['Will it ask about things not in my material?', 'No. Questions come only from the text you paste.'],
            ['What question types are included?', 'A mix of multiple choice, true or false and short answer, each tagged with topic and difficulty.'],
        ])],
    ]),
];

$templates['appointment-request'] = [
    'name' => 'Appointment request',
    'description' => 'A clinic front-desk page that collects appointment preferences and returns a confirmation and what to bring, with no health details.',
    'category' => 'Healthcare',
    'icon' => 'heart-pulse',
    'color' => '#0D9488',
    'content' => $tool([
        'theme' => $theme('#0d9488', ['background' => 'plain', 'width' => 'medium']),
        'hero' => ['title' => 'Request an appointment', 'subtitle' => 'Tell us when suits you and we will confirm a time.', 'tone' => 'subtle'],
        'highlights' => [['danger', 'In an emergency', 'Call your local emergency number. Do not use this form for urgent medical problems.'], ['info', 'Privacy', 'Please do not describe symptoms or medical history here. Our team will ask in a secure way when needed.']],
        'form' => ['title' => 'Your details', 'fields' => 4, 'layout' => '1-1', 'submit' => 'Request appointment', 'running' => 'Sending…'],
        'outputs' => [['Confirmation and what to bring', 'markdown', 'Your confirmation appears here.']],
        'idle' => 'Your confirmation will appear here.',
        'after' => [PageBlocks::faq([
            ['How do I change or cancel?', 'Contact the front desk as early as you can so the time can be offered to someone else.'],
            ['What should I bring?', 'Your confirmation lists what to bring, such as identification and insurance details.'],
        ])],
    ]),
];

return $templates;
