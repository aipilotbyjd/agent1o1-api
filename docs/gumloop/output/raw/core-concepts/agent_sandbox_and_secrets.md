> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Code Sandbox & Secrets

> Secure code execution and credential management for your AI agents.

The Code Sandbox gives your agent the ability to execute Python code and shell commands in a secure, isolated cloud environment. It is **natively enabled** on all agents with no configuration required.

***

## What the Sandbox Can Do

<CardGroup cols={2}>
  <Card title="Run Python Code" icon="python">
    Data analysis, visualizations, computations, file processing, API calls, and more.
  </Card>

  <Card title="Execute Shell Commands" icon="terminal">
    File operations, package installation, running scripts, and system commands.
  </Card>

  <Card title="Read & Write Files" icon="file-pen">
    Create, modify, and organize files within the sandbox filesystem.
  </Card>

  <Card title="Upload & Download" icon="arrows-up-down">
    Move files between your Gumloop storage and the sandbox environment.
  </Card>
</CardGroup>

***

## How It Works

Each conversation runs in its own **secure cloud sandbox** (an isolated VM). The files your agent creates while working stay with that conversation, so if you reopen the chat later they are restored automatically.

Anything that should outlive a single chat lives in one of two durable layers that carry over across all conversations with the same agent: the shared **package environment** and the agent's **workspace folders**.

<Tabs>
  <Tab title="Persistence Model">
    | Scope                                           | What Persists                                                             | Lifetime                                                                                                                |
    | ----------------------------------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
    | **Per-conversation**                            | Working directory, variables, and files the agent generates while working | Saved when the chat ends and restored when you reopen that same conversation                                            |
    | **Shared package environment**                  | Installed packages (`pip install`, `npm install`), cached dependencies    | Shared across every chat on the agent. Members with edit access add packages once and they become available to everyone |
    | **Team workspace** (`.workspace/agent/`)        | Skills and files saved to the shared workspace                            | Shared across all conversations and all members of the agent                                                            |
    | **Personal workspace** (`.workspace/personal/`) | Files you save to your private workspace                                  | Private to you, persists across all your conversations with that agent                                                  |

    <Info>Starting a new conversation gives you a fresh working directory, but your installed packages and workspace files are already available from previous sessions.</Info>
  </Tab>

  <Tab title="Isolation">
    Conversations are isolated from one another:

    * Each chat runs in its own sandbox, so one conversation can never see another conversation's in-progress working files.
    * Sharing across chats happens only through the workspace folders and the shared package environment described above.
    * Your **personal workspace** is private to you. Other members of the same agent cannot see it.
    * Subagents spawned by your agent run in their own sandboxes but mount the same workspace folders and package environment, so they can reach the same shared files and packages.
  </Tab>
</Tabs>

***

## Pre-installed Packages

The sandbox comes with **80+ Python packages** ready to use, so your agent can start working immediately without installing anything.

<AccordionGroup>
  <Accordion title="Data Science & Analysis" icon="chart-line">
    pandas, numpy, scipy, scikit-learn, statsmodels
  </Accordion>

  <Accordion title="Visualization" icon="chart-pie">
    matplotlib, seaborn, plotly, bokeh
  </Accordion>

  <Accordion title="AI & Machine Learning" icon="brain">
    openai, anthropic, google-generativeai, mistralai, llama-index-core, gensim
  </Accordion>

  <Accordion title="Web & APIs" icon="globe">
    requests, aiohttp, beautifulsoup4, scrapy, selenium, playwright
  </Accordion>

  <Accordion title="File Processing" icon="file">
    openpyxl, PyMuPDF, python-docx, Pillow, opencv-python, imageio
  </Accordion>

  <Accordion title="Media Processing" icon="film">
    moviepy, librosa, ffmpeg-python, yt-dlp, soundfile
  </Accordion>

  <Accordion title="Natural Language Processing" icon="language">
    nltk, spacy, textblob
  </Accordion>

  <Accordion title="Database & Cloud" icon="database">
    psycopg2-binary, pymongo, PyMySQL, pyodbc, boto3, google-cloud-storage
  </Accordion>
</AccordionGroup>

<Tip>Need something that isn't pre-installed? Your agent can install it with `pip install package-name`. Installed packages are shared across every chat on the agent, so you only need to install once. Members with edit access to the agent can add packages for everyone; if you only have view access, your own installs are temporary and last for the current session.</Tip>

***

## Execution Limits

| Resource            | Limit                                                                        |
| ------------------- | ---------------------------------------------------------------------------- |
| **Command timeout** | 30 minutes per command                                                       |
| **File ingestion**  | Up to 300 MB per file                                                        |
| **Network access**  | Full internet access (API calls, pip installs, web requests)                 |
| **GUI**             | Headless only. Visualizations must be saved to files (e.g., `plt.savefig()`) |

<Info>The sandbox is designed for data analysis, scripting, and automation tasks. It is not intended for training large ML models or running persistent servers.</Info>

***

## Examples

Here are some common ways to use the Code Sandbox:

<Tabs>
  <Tab title="Data Analysis">
    Ask your agent to analyze data:

    ```text theme={"dark"}
    Analyze this CSV file and create a summary with charts showing
    monthly revenue trends and top-performing products.
    ```

    The agent will use pandas to load the data, perform analysis, and generate visualizations with matplotlib or plotly.
  </Tab>

  <Tab title="API Integration">
    Use secrets to call external APIs:

    ```text theme={"dark"}
    Pull all open support tickets from our Pylon API and create
    a spreadsheet grouped by priority level.
    ```

    The agent accesses your configured secrets as environment variables and makes authenticated API calls.
  </Tab>

  <Tab title="File Processing">
    Transform and process files:

    ```text theme={"dark"}
    Take this PDF report, extract all tables, and convert them
    into a clean Excel spreadsheet with proper formatting.
    ```

    The agent uses libraries like PyMuPDF and openpyxl to read PDFs and generate spreadsheets.
  </Tab>

  <Tab title="Custom Scripts">
    Run complex multi-step scripts:

    ```text theme={"dark"}
    Scrape all product listings from this website, clean the data,
    remove duplicates, and export as a CSV with price comparisons.
    ```

    The agent writes and executes Python scripts, using the shell for any system-level operations needed.
  </Tab>
</Tabs>

***

## Workspace Files

The agent has two persistent workspace folders that carry over across conversations:

* **Team workspace** (`.workspace/agent/`) — shared by everyone with access to the agent. Files saved here by one member are visible to all other members, which makes it ideal for reference data, configuration, or ongoing project assets. Members with **edit** access can write to it; members with **view** access can read it but not change it.
* **Personal workspace** (`.workspace/personal/`) — private to you. Files you save here persist across your own conversations with the agent and are never visible to other members.

Your agent chooses the right folder automatically based on whether a file should be shared or kept private, so you can simply ask it to save something for later.

Workspace files follow the same artifact system as other agent-generated files: they are versioned, previewable, and shareable.

<Info>For more details on file management, versioning, and sharing, see [Agent Artifacts](/core-concepts/agent_artifacts).</Info>

***

## Integration with Apps

The sandbox has access to your connected apps via the pre-installed `gumloop` SDK. Your agent can call any of its configured integrations directly from Python code:

```python theme={"dark"}
from gumloop import Gumloop

client = Gumloop()
result = client.mcp.execute(
    server_id="slack",
    tool_name="send_message",
    arguments={"channel": "#general", "text": "Hello from the sandbox!"}
)
```

This means your agent can combine code execution with any integration, for example: query a database, process the results in Python, then post a summary to Slack.

***

<h2 id="secrets">
  Agent Secrets
</h2>

Agent Secrets let you inject encrypted credentials into the sandbox as environment variables, so your agent can authenticate with external services (APIs, databases, etc.) without ever exposing the raw values.

***

### Adding a Secret to Your Agent

<Steps>
  <Step title="Open Settings and add a secret">
    Navigate to your agent and click the **Settings** tab. Scroll down to the **Secrets** section, expand it, and click **+ Secret**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/IWcPlWNyYD9LhI4_/images/agent-secrets/agent-settings-add-secret.png?fit=max&auto=format&n=IWcPlWNyYD9LhI4_&q=85&s=9c75201f0b951bacc7cc04641e1c360e" alt="Agent Settings page showing the Secrets section expanded with No secrets configured and an arrow pointing to the + Secret button" style={{ maxWidth: '320px' }} width="810" height="1414" data-path="images/agent-secrets/agent-settings-add-secret.png" />
    </Frame>

    Select from your [personal secrets](https://www.gumloop.com/settings/profile/secrets), or create a new one directly from the picker.
  </Step>

  <Step title="Confirm the secret is configured">
    Once added, the secret appears by name in the Secrets section. Your agent now has access to it at runtime.

    <Frame>
      <img src="https://mintcdn.com/agenthub/IWcPlWNyYD9LhI4_/images/agent-secrets/agent-settings-secret-configured.png?fit=max&auto=format&n=IWcPlWNyYD9LhI4_&q=85&s=d27cf7ed41df3d2f7d21f37c531c6403" alt="Agent Settings Secrets section showing Pylon API Key configured" style={{ maxWidth: '480px' }} width="808" height="358" data-path="images/agent-secrets/agent-settings-secret-configured.png" />
    </Frame>

    You can add multiple secrets by clicking **+ Secret** again, or remove one via the three-dot menu.
  </Step>

  <Step title="Prompt the agent to use the secret">
    In the agent chat, ask it to perform a task that requires the credential. The agent accesses the secret as an environment variable (e.g. `os.environ["PYLON_API_KEY"]`) and uses it in code, but it can never read or expose the actual value.

    <Frame>
      <img src="https://mintcdn.com/agenthub/IWcPlWNyYD9LhI4_/images/agent-secrets/agent-chat-secret-usage.png?fit=max&auto=format&n=IWcPlWNyYD9LhI4_&q=85&s=63806aea6d9c928403444cbb86fc1625" alt="Agent chat showing it has access to PYLON_API_KEY and using it to query the Pylon API for tickets created today" style={{ maxWidth: '580px' }} width="1548" height="1188" data-path="images/agent-secrets/agent-chat-secret-usage.png" />
    </Frame>
  </Step>
</Steps>

<Tip>
  If you share an agent that uses personal secrets, other users will be prompted to provide their own values. Your secrets are never exposed.
</Tip>

***

### Two Types of Secrets

<CardGroup cols={2}>
  <Card title="Personal Secrets" icon="user-lock">
    Private to you. No other user can access them. Managed from your [personal secrets settings](https://www.gumloop.com/settings/profile/secrets).
  </Card>

  <Card title="Team Secrets" icon="users">
    Shared across all team members. Available when an agent is in a team space.
  </Card>
</CardGroup>

***

### Team Secrets

For agents in a **team space**, you can use shared secrets that all team members can access.

<Steps>
  <Step title="Move agent to a team space">
    Move your agent into a team (or create it there).
  </Step>

  <Step title="Add a team secret">
    In agent Settings > Secrets, click **+ Secret**. The dropdown shows both **Personal Secrets** and **Team Secrets**.
  </Step>

  <Step title="Select a team secret">
    Pick from the Team Secrets section. All team members will share this value.
  </Step>
</Steps>

<Frame>
  <img src="https://mintcdn.com/agenthub/k-7DjQTkUFuFwjdh/images/agent-secrets/agent-team-secrets.png?fit=max&auto=format&n=k-7DjQTkUFuFwjdh&q=85&s=97004d4a97f73185d7431e9158663881" alt="Secret picker showing Personal Secrets and Team Secrets sections with Pylon API Key under Team Secrets" style={{ maxWidth: '480px' }} width="836" height="1242" data-path="images/agent-secrets/agent-team-secrets.png" />
</Frame>

***

### Runtime Resolution

Secrets resolve based on the **running user**, not the agent owner:

* **Personal secret configured**: other users are prompted to provide their own value
* **Team secret configured**: all team members share the same value

When a user encounters a secret they haven't configured, the chat prompts them to configure it:

<Frame>
  <img src="https://mintcdn.com/agenthub/aDThhcTPQnnWJcde/images/agent-secrets/agent-chat-configure-secrets.png?fit=max&auto=format&n=aDThhcTPQnnWJcde&q=85&s=240231ad50bf66a9773294891bb18314" alt="Chat showing Configure secrets prompt with PYLON_API_KEY needed, a dropdown to select from Personal Secrets or add new, and buttons for Skip, Save for me, and Save to agent" style={{ maxWidth: '580px' }} width="1688" height="1230" data-path="images/agent-secrets/agent-chat-configure-secrets.png" />
</Frame>

Options:

* **Skip**: proceed without the secret
* **Save for me**: map a personal secret for this user only
* **Save to agent**: update the agent's default binding

Users can also manage their active secrets during a conversation using the **Secrets** button in the chat composer:

<Frame>
  <img src="https://mintcdn.com/agenthub/aDThhcTPQnnWJcde/images/agent-secrets/agent-chat-secrets-popover.png?fit=max&auto=format&n=aDThhcTPQnnWJcde&q=85&s=a43d6736324e3d890a22630daa293356" alt="Chat composer Secrets popover showing Your secrets with Pylon API Key mapped to PYLON_API_KEY" style={{ maxWidth: '580px' }} width="1650" height="496" data-path="images/agent-secrets/agent-chat-secrets-popover.png" />
</Frame>

***

### Comparison

|                    | Personal Secrets                                                      | Team Secrets                           |
| ------------------ | --------------------------------------------------------------------- | -------------------------------------- |
| **Visibility**     | Only you                                                              | All team members                       |
| **Where managed**  | [Personal settings](https://www.gumloop.com/settings/profile/secrets) | Team settings                          |
| **Use case**       | Private API keys, personal tokens                                     | Shared service accounts, org-wide keys |
| **Agent location** | Personal or team space                                                | Team space only                        |
| **Resolution**     | Per-user (each provides their own)                                    | Shared (one value for all)             |

***

### FAQ

<AccordionGroup>
  <Accordion title="Can the agent see my secret values?" icon="eye-slash">
    No. Secrets are injected as environment variables at runtime. The agent can reference them by name (`os.environ["MY_KEY"]`) but never sees the actual value. Values are encrypted and never shown to the agent.
  </Accordion>

  <Accordion title="Can I use both personal and team secrets on the same agent?" icon="layer-group">
    Yes. An agent in a team space can use both types. Team secrets share one value for all members. Personal secrets require each user to provide their own value. If you run an agent that has a personal secret you haven't set up yet, the chat will prompt you to bind your own (see [Runtime Resolution](#runtime-resolution)).
  </Accordion>

  <Accordion title="Do secrets persist across conversations?" icon="rotate">
    Yes. Secrets are bound to the agent configuration. They are available every time the agent runs code.
  </Accordion>

  <Accordion title="How do I create a new personal secret?" icon="plus">
    Go to [gumloop.com/settings/profile/secrets](https://www.gumloop.com/settings/profile/secrets) and add one. It will then appear in the secret picker when configuring agents.
  </Accordion>
</AccordionGroup>
