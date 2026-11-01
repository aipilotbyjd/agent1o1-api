> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Choosing the Right AI Node

> Select the optimal AI node for your workflow needs

<CardGroup cols={3}>
  <Card title="Ask AI" icon="wand-magic-sparkles" href="#ask-ai">
    Custom flexible responses
  </Card>

  <Card title="Categorizer" icon="layer-group" href="#categorizer">
    Consistent classification
  </Card>

  <Card title="Extract Data" icon="database" href="#extract-data">
    Structured field extraction
  </Card>

  <Card title="Scorer" icon="star" href="#scorer">
    Objective evaluation
  </Card>

  <Card title="Analyze Video" icon="video" href="#analyze-video">
    Video understanding
  </Card>

  <Card title="Analyze Image" icon="image" href="#analyze-image">
    Image analysis
  </Card>

  <Card title="AI List Sorter" icon="arrow-down-wide-short" href="#ai-list-sorter">
    Intelligent prioritization
  </Card>
</CardGroup>

## Why Use Specialized Nodes?

While Ask AI offers maximum flexibility, specialized nodes provide key advantages for automation:

<AccordionGroup>
  <Accordion title="Predictable Output Structure" icon="grid">
    * Ask AI returns free-form text requiring parsing
    * Specialized nodes deliver consistent, automation-ready outputs
    * Example: Extract Data always outputs specified fields; Ask AI needs complex prompts and parsing
  </Accordion>

  <Accordion title="Optimized for Common Tasks" icon="bolt">
    * Pre-engineered for specific workflows (Summarizer directly condenses text)
    * More reliable than achieving consistency with Ask AI
    * Simpler workflow setup
  </Accordion>
</AccordionGroup>

***

## Ask AI

*Custom tasks requiring flexible responses*

<Info>Best for workflows needing flexible processing, complex transformations, or nuanced understanding where other nodes are too rigid.</Info>

<Tabs>
  <Tab title="Email Generation">
    **Input:** Customer inquiry stream\
    **Prompt:** `Write a response to these customer queries about {topic}`\
    **Context:** Customer data, company guidelines, product details\
    **Output:** Customized responses per inquiry
  </Tab>

  <Tab title="Data Transformation">
    **Input:** Raw business metrics\
    **Prompt:** `Convert this raw data into a quarterly report highlighting key trends`\
    **Context:** Previous reports, reporting guidelines\
    **Output:** Formatted reports per dataset
  </Tab>

  <Tab title="Content Localization">
    **Input:** Marketing content in English\
    **Prompt:** `Translate and adapt this content for {target_market}`\
    **Context:** Cultural guidelines, local preferences\
    **Output:** Market-specific content versions
  </Tab>
</Tabs>

***

## AI List Sorter

*Intelligent list ordering beyond simple filtering*

<Info>Best for complex prioritization, multi-factor sorting, subjective ordering, or any workflow needing smart list organization.</Info>

<Tabs>
  <Tab title="Sales Pipeline">
    **Input:** Sales opportunity stream\
    **Prompt:** `Sort by deal size, close probability, and urgency`\
    **Output:** Prioritized opportunity list\
    **Next Steps:** Team assignments, follow-up scheduling
  </Tab>

  <Tab title="Feature Requests">
    **Input:** Product backlog items\
    **Prompt:** `Sort by user impact, development effort, and strategic alignment`\
    **Output:** Prioritized feature list\
    **Next Steps:** Sprint planning, resource allocation
  </Tab>

  <Tab title="Support Queue">
    **Input:** Active support tickets\
    **Prompt:** `Sort by business impact, customer tier, and issue severity`\
    **Output:** Prioritized ticket queue\
    **Next Steps:** Team assignments, SLA monitoring
  </Tab>
</Tabs>

***

## Categorizer

*Reliable, consistent content classification*

<Info>Best for automated content routing, large-scale data classification, real-time sorting, or any workflow requiring reliable categorization.</Info>

<Tabs>
  <Tab title="Support Tickets">
    **Input:** Support ticket stream\
    **Categories:**

    * Bug Report: Issues with existing features
    * Feature Request: New functionality asks
    * Account: Login, access, billing issues
    * Security: Security concerns or breaches

    **Output:** Category + justification per ticket\
    **Next Steps:** Route to appropriate team, set priorities
  </Tab>

  <Tab title="Content Moderation">
    **Input:** User-generated content feed\
    **Categories:**

    * Safe: Appropriate content
    * Needs Review: Potentially inappropriate
    * Blocked: Violates guidelines

    **Output:** Category per content piece\
    **Next Steps:** Automatic approval/blocking/review routing
  </Tab>

  <Tab title="Email Processing">
    **Input:** Incoming email stream\
    **Categories:**

    * Sales Lead: Potential customer inquiries
    * Support: Existing customer issues
    * Partnership: Business collaboration requests
    * Other: General inquiries

    **Output:** Category per email\
    **Next Steps:** Department routing, trigger responses
  </Tab>
</Tabs>

***

## Extract Data

*Pull specific information from text*

<Info>Best for automated data extraction pipelines, form/document processing, structured data generation, or converting unstructured text to data.</Info>

<Tabs>
  <Tab title="Invoice Processing">
    **Input:** Invoice stream\
    **Fields:** Invoice Number, Date, Amount, Company Name, Due Date\
    **Output:** Structured data per field\
    **Next Steps:** Update accounting system, trigger payments
  </Tab>

  <Tab title="Resume Processing">
    **Input:** Resume batch\
    **Fields:** Name, Email, Skills, Experience (years), Education\
    **Output:** Structured candidate data\
    **Next Steps:** Match to job openings, schedule interviews
  </Tab>

  <Tab title="Product Reviews">
    **Input:** Customer reviews feed\
    **Fields:** Product Name, Rating, Pros, Cons, Feature Mentions\
    **Output:** Structured review data\
    **Next Steps:** Update product analytics, trigger alerts
  </Tab>
</Tabs>

***

## Extract to Table

*Automated spreadsheet population*

<Info>Best for automated record keeping, database population workflows, report generation, or any process requiring spreadsheet updates.</Info>

<Tabs>
  <Tab title="Lead Management">
    **Input:** Various lead sources (forms, emails, calls)\
    **Columns:** Date | Name | Email | Source | Interest | Status\
    **Output:** Populated rows per lead\
    **Next Steps:** Lead scoring, sales assignments
  </Tab>

  <Tab title="Inventory Tracking">
    **Input:** Product updates, stock alerts\
    **Columns:** SKU | Location | Quantity | Last Updated | Reorder Status\
    **Output:** Updated inventory rows\
    **Next Steps:** Reorder triggers, status reports
  </Tab>

  <Tab title="Event Registration">
    **Input:** Registration forms, email RSVPs\
    **Columns:** Event | Attendee | Email | Ticket Type | Special Needs\
    **Output:** Attendee list rows\
    **Next Steps:** Send confirmations, plan logistics
  </Tab>
</Tabs>

***

## Summarizer

*Consistent content condensation*

<Info>Best for content digest automation, document processing pipelines, or any workflow needing shorter content versions.</Info>

<Tabs>
  <Tab title="News Digest">
    **Input:** News article stream\
    **Output:** Concise summaries\
    **Next Steps:** Newsletter generation, alert system
  </Tab>

  <Tab title="Meeting Notes">
    **Input:** Transcription feeds\
    **Output:** Key points and action items\
    **Next Steps:** Task creation, update tracking
  </Tab>

  <Tab title="Research Reports">
    **Input:** Technical documents\
    **Output:** Executive summaries\
    **Next Steps:** Knowledge base updates, notifications
  </Tab>
</Tabs>

***

## Scorer

*Standardized evaluation processes*

<Info>Best for quality control automation, performance monitoring, automated assessments, compliance checking, or any process requiring numerical evaluation.</Info>

<Tabs>
  <Tab title="Customer Service">
    **Input:** Support conversation stream\
    **Criteria:**

    * Solution Quality (0-40): Resolution effectiveness
    * Communication (0-30): Clarity and professionalism
    * Efficiency (0-30): Response time and conciseness

    **Output:** Score + justification per conversation\
    **Next Steps:** Performance tracking, training recommendations
  </Tab>

  <Tab title="Content Quality">
    **Input:** Articles/posts stream\
    **Criteria:**

    * Research Quality (0-40): Depth and accuracy
    * Writing Style (0-30): Clarity and engagement
    * SEO Optimization (0-30): Keywords and structure

    **Output:** Score + breakdown per piece\
    **Next Steps:** Publishing decisions, improvement requests
  </Tab>
</Tabs>

***

## Analyze Video

*Automated video content processing*

<Info>Best for video content management, training material processing, moderation workflows, marketing content analysis, or any automation requiring video understanding.</Info>

<Tabs>
  <Tab title="Product Demos">
    **Input:** Product demonstration video stream\
    **Prompt:** `Extract key features and specs demonstrated`\
    **Output:** Detailed feature lists per video\
    **Next Steps:** Update product docs, create timestamps
  </Tab>

  <Tab title="Training Videos">
    **Input:** Educational content videos\
    **Prompt:** `List all steps and procedures shown`\
    **Output:** Step-by-step process documentation\
    **Next Steps:** Create guides, update knowledge base
  </Tab>

  <Tab title="Content Moderation">
    **Input:** User-generated video content\
    **Prompt:** `Identify any inappropriate content or safety concerns`\
    **Output:** Content analysis and flag recommendations\
    **Next Steps:** Approval/rejection, creator notifications
  </Tab>
</Tabs>

***

## Analyze Image

*Automated image content processing*

<Info>Best for document digitization, image catalog management, visual content monitoring, or any process requiring image understanding.</Info>

<Tabs>
  <Tab title="Document Processing">
    **Input:** Scanned document stream\
    **Prompt:** `Extract all text and form fields`\
    **Output:** Extracted text and data\
    **Next Steps:** Database updates, verification workflows
  </Tab>

  <Tab title="Product Photos">
    **Input:** E-commerce product images\
    **Prompt:** `Describe product features and characteristics`\
    **Output:** Detailed product descriptions\
    **Next Steps:** Catalog updates, listing generation
  </Tab>
</Tabs>

***

## Node Comparison

| Aspect            | Ask AI                   | Specialized Node            |
| ----------------- | ------------------------ | --------------------------- |
| **Setup**         | Complex prompting needed | Pre-configured              |
| **Output Format** | Requires parsing         | Automation-ready            |
| **Consistency**   | May vary between runs    | Highly consistent           |
| **Best For**      | Unique, flexible needs   | High-volume, standard tasks |

<Tip>
  **Selection Principle:** Choose specialized nodes for repeated tasks requiring consistent output. Use Ask AI when you need flexibility or have unique requirements that don't fit standard patterns.
</Tip>
