> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Domain Restrictions

> Require OAuth connections to a given app to use an email address from a domain your organization controls.

Domain Restrictions make sure every new OAuth connection to a given app uses
an email from a domain your organization owns. This prevents users from
connecting personal accounts to business-critical apps by mistake.

<Frame>
  <img src="https://mintcdn.com/agenthub/LCj5sx3bXOPCoTSu/images/enterprise-features/app-policies/domain-restrictions.png?fit=max&auto=format&n=LCj5sx3bXOPCoTSu&q=85&s=8111a54ff07824a233cd8aee15b092e1" alt="Domain Restrictions tab showing a Google Sheets domain restriction allowing gumloop.com with an enabled toggle" width="1822" height="644" data-path="images/enterprise-features/app-policies/domain-restrictions.png" />
</Frame>

## What it does

When someone in your organization connects a new credential to a protected
app, Gumloop inspects the email address of the account they're authorizing.
If that email's domain isn't on the allowlist you've configured, the OAuth
flow fails and their connection is not saved.

Domain Restrictions only affect **new** connections. Existing credentials that
were already connected before the restriction was added continue to work —
revoke them manually if you want to remove them.

<Info>
  Not every app supports Domain Restrictions. The **Available Apps** section of
  the tab lists only the apps where Gumloop can reliably read the connecting
  user's email from the provider (Google Workspace, Slack, Microsoft, etc.).
</Info>

## Creating a restriction

<Steps>
  <Step title="Open the Domain Restrictions tab">
    Go to [Settings → Organization → App Policies → Domain Restrictions](https://gumloop.com/settings/organization/app-policies?tab=domain-restrictions).
  </Step>

  <Step title="Pick an app">
    Click an app card in **Available Apps**, or click the `+` next to an
    already-restricted app to add another allowed domain.
  </Step>

  <Step title="Enter the required domain">
    Enter a domain like `yourcompany.com`. Any email whose domain matches
    exactly will be allowed; everything else will be blocked.
  </Step>

  <Step title="Confirm">
    Click **Add Restriction**. The restriction is enforced immediately for new
    OAuth connections.
  </Step>
</Steps>

<Tip>
  You can add more than one restriction per app if you want to allow multiple
  domains (for example, `yourcompany.com` and `yourcompany.co.uk`). Each
  restriction adds one domain to the allowlist for that app.
</Tip>

## Enable, disable, delete

Each restriction has an **Enabled** toggle. Disabling a restriction pauses
enforcement without deleting the rule, so you can turn it back on later. You
can also delete a restriction from its detail page.

## What end users see

When someone tries to connect an account that doesn't match, the OAuth flow
ends with an error explaining that their organization requires a different
email domain for this app. They can retry the connection with an account on an
allowed domain.

## Related

<CardGroup cols={2}>
  <Card title="App Claims" icon="building-lock" href="/enterprise-features/app-policies/app-claims">
    Restrict connections by provider workspace rather than by email domain.
  </Card>

  <Card title="Credentials" icon="key" href="/core-concepts/credentials">
    Learn how OAuth connections are stored and used across Gumloop.
  </Card>
</CardGroup>
