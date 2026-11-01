> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Skills

> Upload, update, and download agent skills as files.

`gumloop skills` lets you manage agent [skills](/core-concepts/skills) as files on disk. Each skill is one or more files (markdown, JSON, anything) that an agent can read at runtime.

## List skills

```bash theme={"dark"}
gumloop skills list
gumloop skills list --search retrieval --limit 50
```

Prints `ID`, `NAME`, `TEAM`, `USAGE` (usage count), and `UPDATED`. If the response is paginated, the next cursor is printed at the bottom.

| Flag       | Description                                           |
| ---------- | ----------------------------------------------------- |
| `--search` | Filter skills by query string.                        |
| `--server` | Filter to skills related to a specific MCP server ID. |
| `--limit`  | Maximum number of skills to return.                   |
| `--cursor` | Pagination cursor from a previous list call.          |
| `--json`   | Print the raw response payload.                       |

## Create a skill

Upload one or more files as a new skill:

```bash theme={"dark"}
gumloop skills create ./my-skill.md
gumloop skills create skills/*.md
```

The skill is created with all of the provided files atomically. The new skill ID is printed on success.

## Update a skill

Replace the files attached to an existing skill:

```bash theme={"dark"}
gumloop skills update skill_abc ./new-version.md
```

<Warning>
  `update` is a **full replace**, not a merge. Any file that was attached to the skill but isn't in the new upload is removed. To add a file without losing the existing ones, pass all of them: `gumloop skills update skill_abc existing.md new.md`.
</Warning>

<Tip>Grab the skill ID from `gumloop skills list`.</Tip>

## Download a skill

```bash theme={"dark"}
gumloop skills download skill_abc
```

By default the file is written to the current directory under its original filename. Override the destination with `-o`:

| Flag             | Description                                                |
| ---------------- | ---------------------------------------------------------- |
| `-o`, `--output` | File or directory to write to. Use `-` to write to stdout. |
| `--version-id`   | Download a specific skill version.                         |
| `--json`         | Print download metadata as JSON (path + bytes).            |

```bash theme={"dark"}
gumloop skills download skill_abc -o ./local-name.md
gumloop skills download skill_abc -o -                    # write to stdout
gumloop skills download skill_abc --version-id skv_xyz
```
