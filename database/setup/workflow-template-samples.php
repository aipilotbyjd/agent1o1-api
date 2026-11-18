<?php

/**
 * Example answers for each starter workflow's run form, keyed by template
 * slug and then by Input step key. A sample run (`SampleRuns`) fills the
 * form with these, so a new user can watch a template work before typing
 * anything or connecting an app. Everything here is made up: the people and
 * companies are fictional, and app IDs are placeholders, because a sample
 * run never calls an app.
 *
 * Templates with nothing to show without an app have no entry: those started
 * by a trigger, and those whose first step reads data from an app (a sheet,
 * a calendar, a repository), since a sample run would have no data to read.
 *
 * @return array<string, array<string, string>>
 */
$meetingNotes = "Attendees: Priya (Sales), Tom (Product), Lena (Support).\n"
    ."- The Q3 onboarding emails go out on 4 September; Priya owns the final copy.\n"
    ."- Tom will ship the CSV export fix by Friday.\n"
    ."- Support saw 40 tickets about password resets last week; Lena to draft a help article.\n"
    .'- Next check-in: Tuesday 10:00.';

$helpCenter = "Refunds: full refund within 30 days of purchase, no questions asked.\n"
    ."Shipping: orders ship within 2 business days; tracking is emailed when they leave.\n"
    .'Passwords: reset from the login page with "Forgot password"; links expire after 1 hour.';

return [
    'lead-intake-and-scoring' => [
        'first_name' => 'Priya',
        'last_name' => 'Shah',
        'email' => 'priya.shah@acmerobotics.example',
        'company' => 'Acme Robotics',
        'message' => 'We are rolling out to 3 warehouses next quarter and want pricing for 10 seats and a demo next week.',
        'ideal_customer' => 'Logistics and manufacturing companies with 50 to 500 staff. Buyers are operations leaders with budget for automation.',
    ],
    'sales-call-prep-brief' => [
        'prospect' => 'Marcus Lee, Head of Operations',
        'company' => 'Northwind Freight',
        'notes' => 'Regional freight company, 220 staff. Posted two dispatcher roles last month. Uses spreadsheets to plan routes.',
        'your_email' => 'you@example.com',
    ],
    'cold-email-sequence-drafts' => [
        'prospect_email' => 'dana@brightpath.example',
        'prospect_name' => 'Dana Ortiz, VP Customer Success',
        'trigger' => 'BrightPath just announced a 40% jump in customers and is hiring 6 support agents.',
        'offer' => 'An AI assistant that drafts first replies to support tickets, cutting response time in half.',
    ],
    'meeting-follow-up-and-crm-note' => [
        'customer_email' => 'sam@cobaltlabs.example',
        'hubspot_contact_id' => '1234567',
        'notes' => 'Sam wants SSO and audit logs before signing. Budget approved for Q4. Next step: send the security pack and book a call with IT.',
    ],
    'blog-to-social-posts' => [
        'article' => 'Our latest post explains how small teams can save 5 hours a week by automating lead follow-up, with three real examples and a checklist.',
        'voice' => 'Friendly, practical and direct. No hype, no emojis.',
    ],
    'seo-content-brief-to-doc' => [
        'keyword' => 'invoice automation for small business',
        'audience' => 'Owners of 5 to 50 person businesses who still process invoices by hand.',
        'product' => 'Start a free trial of our invoicing workflow.',
    ],
    'ad-copy-variants-to-sheet' => [
        'product' => 'A meal-planning app that builds a weekly shopping list in 2 minutes.',
        'audience' => 'Busy parents who cook at home 4 or more nights a week.',
        'spreadsheet_id' => 'sample-sheet-id',
    ],
    'support-email-triage-and-reply' => [
        'customer_email' => 'alex@example.com',
        'subject' => 'Charged twice this month',
        'message' => 'Hi, I was billed twice for my March subscription. Can you refund the extra charge? Order 48213.',
        'help_content' => $helpCenter,
    ],
    'customer-feedback-theme-report' => [
        'feedback' => "\"Setup took me 10 minutes, love it.\"\n\"The mobile app logs me out every day.\"\n\"Please add a dark mode.\"\n\"Logging out on mobile again, very annoying.\"\n\"Support answered in 5 minutes, great.\"",
        'period' => 'Last 7 days',
        'notion_parent_page' => 'sample-page-id',
    ],
    'ticket-to-github-issue' => [
        'repo' => 'acme/web-app',
        'report' => 'When I upload a PNG larger than 5 MB on the profile page, the spinner never stops and nothing is saved. Chrome on Windows.',
    ],
    'negative-review-alert-and-reply' => [
        'review' => 'Delivery was 4 days late and nobody answered my emails. The product itself is fine.',
        'rating' => '2',
        'reviewer' => 'Chris',
    ],
    'resume-screening-with-shortlist' => [
        'job_description' => 'Customer Success Manager. Must have: 3+ years in B2B SaaS success, owns renewals, comfortable with data. Nice to have: HubSpot.',
        'resume' => 'Jordan Kim. Customer Success Manager at Fieldly (4 years): managed 60 accounts, 96% renewal rate, built health dashboards in HubSpot. Before that, support lead at Tappit.',
        'candidate_email' => 'jordan.kim@example.com',
    ],
    'new-hire-onboarding-kickoff' => [
        'hire_name' => 'Maya Chen',
        'role' => 'Product Designer, Growth team',
        'start_date' => 'Monday 6 October',
        'manager_email' => 'manager@example.com',
    ],
    'interview-notes-to-scorecard' => [
        'candidate' => 'Leo Martins',
        'competencies' => 'Problem solving, communication, ownership',
        'notes' => 'Walked through a pricing-page redesign clearly. Asked good questions about metrics. Hesitant when asked about a project that failed.',
        'spreadsheet_id' => 'sample-sheet-id',
    ],
    'invoice-capture-and-approval' => [
        'invoice_text' => "Invoice INV-2041 from Brightline Supplies\nDate: 2 September\nOffice chairs x4: 1,120.00 USD\nShipping: 60.00 USD\nTotal due: 1,180.00 USD by 2 October",
        'spreadsheet_id' => 'sample-sheet-id',
    ],
    'overdue-invoice-reminder' => [
        'customer_name' => 'Harbor Coffee Co.',
        'customer_email' => 'accounts@harborcoffee.example',
        'invoice_number' => 'INV-1088',
        'amount' => '2,450.00 USD',
        'days_overdue' => '12',
        'payment_link' => 'https://pay.example.com/inv-1088',
    ],
    'expense-policy-review' => [
        'policy' => 'Meals up to 50 USD per person. Economy flights only. Receipts required for anything over 25 USD.',
        'expenses' => "Client dinner, 3 people: 210 USD (receipt)\nTaxi to airport: 42 USD (no receipt)\nFlight to Austin, business class: 980 USD",
        'finance_email' => 'finance@example.com',
    ],
    'monthly-financial-summary' => [
        'figures' => 'Revenue 182,000 USD. Expenses 141,000 USD. Cash 520,000 USD. New customers 34.',
        'previous' => 'Revenue 168,000 USD. Expenses 139,000 USD. Cash 486,000 USD. New customers 29.',
        'recipients' => 'leadership@example.com',
    ],
    'shopify-order-issue-resolution' => [
        'order_id' => '5012345678',
        'customer_email' => 'taylor@example.com',
        'complaint' => 'My order arrived with a cracked mug. Can I get a replacement?',
        'policy' => 'Damaged items are replaced free within 30 days. Ask for a photo of the damage.',
    ],
    'new-product-listing-draft' => [
        'facts' => 'Stoneware coffee mug, 350 ml, dishwasher safe, matte sage glaze, handmade in Portugal.',
        'price' => '24.00 USD',
        'vendor' => 'Casa Clay',
    ],
    'pull-request-review-summary' => [
        'repo' => 'acme/web-app',
        'pull_number' => '42',
    ],
    'incident-postmortem-to-doc' => [
        'title' => 'Checkout errors on 3 September',
        'notes' => "14:02 alerts for 500s on /checkout\n14:10 traced to an expired payment-provider certificate\n14:25 certificate renewed, errors stop\nAbout 180 failed checkouts; no data lost.",
    ],
    'it-request-to-jira' => [
        'project' => 'IT',
        'requester' => 'sam@example.com',
        'request' => 'My laptop will not connect to the office VPN since this morning. It says "authentication failed".',
    ],
    'contract-risk-review' => [
        'contract' => 'Term: 3 years, auto-renews unless cancelled 90 days before. Liability: unlimited for the customer. Payment: net 15. Data may be shared with sub-processors without notice.',
        'priorities' => 'Avoid auto-renewal traps and unlimited liability; we need net 30.',
        'requester_email' => 'you@example.com',
    ],
    'compliance-gap-check' => [
        'framework' => 'SOC 2',
        'document' => 'Access policy: engineers get production access when they join. Passwords must be 8 characters. Access is reviewed when someone leaves.',
        'notion_parent_page' => 'sample-page-id',
    ],
    'property-listing-package' => [
        'facts' => '3 bed, 2 bath townhouse, 1,450 sq ft, renovated kitchen, south-facing garden, 5 minutes walk to the train station.',
    ],
    'showing-follow-up' => [
        'lead_email' => 'robin@example.com',
        'lead_name' => 'Robin',
        'property' => '12 Elm Street, 3 bed townhouse',
        'feedback' => 'Loved the garden, worried the second bedroom is small. Asked about school catchment.',
    ],
    'lesson-plan-to-doc' => [
        'subject' => 'Science: the water cycle',
        'level' => 'Grade 5',
        'minutes' => '45',
        'goals' => 'Students can name the four stages of the water cycle and explain evaporation with an example.',
    ],
    'quiz-from-material' => [
        'material' => 'Photosynthesis is how plants turn light, water and carbon dioxide into glucose and oxygen. It happens in chloroplasts, which contain chlorophyll.',
        'questions' => '5',
        'teacher_email' => 'you@example.com',
    ],
    'assignment-feedback-draft' => [
        'assignment' => 'Write a 300-word persuasive letter to the city council about a local issue.',
        'rubric' => 'Clear position (3), evidence (3), structure (2), grammar (2).',
        'submission' => 'Dear council, our park needs more bins. Every weekend there is litter everywhere and animals eat it. Other parks have more bins and are cleaner. Please add five bins.',
        'teacher_email' => 'you@example.com',
    ],
    'appointment-reminder-sms' => [
        'phone' => '+15555550123',
        'practice' => 'Riverside Dental',
        'when' => 'Thursday 9 October at 10:30',
        'where' => '24 River Road, Suite 3',
        'contact' => '+1 555 555 0100',
    ],
    'intake-form-front-desk-checklist' => [
        'intake' => 'Name: Ana Silva. New patient. Allergies: penicillin. Insurance: BlueCare, card photo not attached. Reason: tooth pain lower left.',
        'manager_email' => 'manager@example.com',
    ],
    'meeting-notes-to-actions' => [
        'title' => 'Weekly product sync',
        'transcript' => $meetingNotes,
        'notion_parent_page' => 'sample-page-id',
    ],
    'weekly-team-update' => [
        'notes' => "Shipped: CSV export fix, new pricing page.\nIn progress: mobile login bug.\nWin: closed Northwind Freight (10 seats).\nBlocked: waiting on legal for the new terms.",
        'team_email' => 'team@example.com',
    ],
    'sop-from-process-description' => [
        'process_name' => 'Refund a customer',
        'description' => 'Support checks the order in Stripe, confirms it is within 30 days, refunds it, then replies to the customer and tags the ticket "refunded".',
    ],
    'ticket-classify-and-route' => [
        'customer' => 'Jamie, Harbor Coffee Co.',
        'subject' => 'Cannot log in after password reset',
        'message' => 'I reset my password but the new one does not work and now I am locked out. We have a shift starting in an hour.',
    ],
    'review-sentiment-response' => [
        'review' => 'Great selection and the staff were lovely, but the checkout queue took 20 minutes.',
        'reviewer' => 'Morgan',
    ],
    'multilingual-support-reply' => [
        'customer_email' => 'lucia@example.com',
        'customer_language' => 'Spanish',
        'team_language' => 'English',
        'message' => 'Hola, mi pedido llegó incompleto: faltan dos tazas. ¿Pueden enviarlas?',
        'help_content' => $helpCenter,
    ],
    'inbound-email-to-crm-contact' => [
        'email_text' => "Hi, I'd like a quote for 25 seats.\n\nThanks,\nElena Rossi\nHead of Operations, Verde Logistics\n+39 02 5555 0101\nelena.rossi@verdelogistics.example",
    ],
    'refund-request-with-approval' => [
        'payment_intent_id' => 'pi_sample123',
        'customer_email' => 'alex@example.com',
        'reason' => 'Bought the annual plan by mistake, meant to pick monthly. Asked within 3 days.',
        'policy' => 'Full refund within 14 days of purchase for annual plans.',
    ],
];
