> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Brain

> Search your Company Brain's indexed knowledge sources from the terminal.

[Company Brain](/core-concepts/brain) is your organization's knowledge base — the documents, messages, and files you've connected and indexed. `gumloop brain` runs the same hybrid (semantic + keyword) search your agents use, straight from your shell.

<Note>Brain is available on the **Pro** and **Enterprise** plans, and each search consumes Gumloop credits.</Note>

## Search

```bash theme={"dark"}
gumloop brain search "onboarding process"
```

The query returns the most relevant snippets across every source you can access — your Personal sources plus any Team and Organization sources shared with you.

```text theme={"dark"}
SCORE   SOURCE          TITLE                   URL
0.871   notion          Onboarding Checklist    https://www.notion.so/Onboarding-Checklist-2f1a9c7e
0.804   google_drive    New Hire Handbook       https://docs.google.com/document/d/1a2b3c
```

### Options

| Flag       | Description                                                                                                                                                                                                          |
| ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--limit`  | Maximum number of results to return (1–50). Defaults to 8.                                                                                                                                                           |
| `--source` | Filter by source type. Repeat the flag to allow several, e.g. `--source notion --source slack`. Valid values: `notion`, `google_drive`, `slack`, `github`, `confluence`, `direct_file_uploads`, `gumloop_artifacts`. |
| `--json`   | Print the raw response payload instead of the table.                                                                                                                                                                 |

```bash theme={"dark"}
gumloop brain search "pricing" --limit 5 --source notion --json
```

The `--json` output includes the full result objects — `document_id`, `source`, `title`, `content`, `url`, `score`, `updated_at`, `owner_name`, `owner_email`, `parent_title`, and `metadata` — which is handy for piping into `jq` or another tool.

## Related

<CardGroup cols={2}>
  <Card title="Company Brain" icon="brain" href="/core-concepts/brain">
    How Brain indexes your knowledge and how agents use it.
  </Card>

  <Card title="Brain API" icon="code" href="/api-reference/brain/search">
    Search Brain over the REST API.
  </Card>
</CardGroup>
