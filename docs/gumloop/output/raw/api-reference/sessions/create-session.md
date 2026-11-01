> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Create session

> Create a new session for an agent. When `input` is provided, the message is enqueued and the agent begins processing — the response returns `202` with the session in `processing` or `queued` state. When `input` is omitted, an idle session stub is created and the response returns `201`.

`agent_id` also accepts the reserved aliases `gumball` and `analytics`, which resolve to your personal Gumball and analytics agents (created on first use).

### Streaming the response

`api.gumloop.com` only serves the non-streaming response above. To stream agent output as it's produced, send the same request body (with `stream: true`) to the streaming host instead:

```
POST https://ws.gumloop.com/api/v1/agents/{agent_id}/sessions
```

The response is `text/event-stream` (Server-Sent Events). With the Python SDK, `client.sessions.stream(agent_id, input="...")` routes to `ws.gumloop.com` automatically and yields parsed `StreamEvent` objects.

If you send `stream: true` to `api.gumloop.com` by mistake, the response is a `400` whose body contains the correct streaming host so you can retry against it.




## OpenAPI

````yaml post /agents/{agent_id}/sessions
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/sessions:
    post:
      tags:
        - Sessions
      summary: Create session
      description: >
        Create a new session for an agent. When `input` is provided, the message
        is enqueued and the agent begins processing — the response returns `202`
        with the session in `processing` or `queued` state. When `input` is
        omitted, an idle session stub is created and the response returns `201`.


        `agent_id` also accepts the reserved aliases `gumball` and `analytics`,
        which resolve to your personal Gumball and analytics agents (created on
        first use).


        ### Streaming the response


        `api.gumloop.com` only serves the non-streaming response above. To
        stream agent output as it's produced, send the same request body (with
        `stream: true`) to the streaming host instead:


        ```

        POST https://ws.gumloop.com/api/v1/agents/{agent_id}/sessions

        ```


        The response is `text/event-stream` (Server-Sent Events). With the
        Python SDK, `client.sessions.stream(agent_id, input="...")` routes to
        `ws.gumloop.com` automatically and yields parsed `StreamEvent` objects.


        If you send `stream: true` to `api.gumloop.com` by mistake, the response
        is a `400` whose body contains the correct streaming host so you can
        retry against it.
      operationId: createSession
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent to start a session on. Also accepts the reserved
            aliases `gumball` and `analytics`.
      requestBody:
        required: false
        content:
          application/json:
            schema:
              type: object
              properties:
                input:
                  type: string
                  description: >-
                    The first user message for the session. Also accepted as
                    `message` for backwards compatibility. When omitted, an idle
                    session is created with no messages.
                  example: Research Acme Corp and draft a brief.
                session_id:
                  type: string
                  description: >-
                    Caller-supplied session ID. When omitted, the server
                    generates one. If provided and the ID already exists, the
                    request returns `409 session_already_exists`.
                  example: sess_xYz789AbCd
                metadata:
                  type: object
                  description: >-
                    Arbitrary key/value metadata attached to the session. Stored
                    under `metadata.client`.
                stream:
                  type: boolean
                  default: false
                  description: >-
                    Must be `false` (or omitted) when calling `api.gumloop.com`.
                    Set to `true` only when calling `ws.gumloop.com` (see the
                    streaming section above).
      responses:
        '201':
          description: >-
            Idle session created. Returned when the request body has no `input`
            — a session stub is created and no agent run is started.
          content:
            application/json:
              schema:
                type: object
                properties:
                  session:
                    type: object
                    properties:
                      id:
                        type: string
                        example: sess_xYz789AbCd
                      agent_id:
                        type: string
                        example: abc123DEFghiJKL
                      messages:
                        type: array
                        items:
                          type: object
                      created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-15T14:32:00Z'
                      state:
                        type: string
                        nullable: true
                        enum:
                          - processing
                          - completed
                          - failed
                          - queued
                          - idle
                          - approval_required
                        example: idle
                      agent_name:
                        type: string
                        nullable: true
                        example: Sales research agent
                      agent_team_id:
                        type: string
                        nullable: true
                        example: team_4f8c92ab
                      agent_creator_user_id:
                        type: string
                        nullable: true
                        example: user_2b9d71f0
                      agent_icon_url:
                        type: string
                        nullable: true
                        example: null
                      agent_tools:
                        type: array
                        description: >-
                          Tools available to the agent. Secret references are
                          stripped before being returned.
                        items:
                          type: object
                      participants:
                        type: object
                        description: Map of participant user IDs to participant metadata.
                      creator:
                        type: object
                        nullable: true
                        description: >-
                          Creator of the session. `null` when no creator is
                          recorded.
                        properties:
                          id:
                            type: string
                            nullable: true
                          first_name:
                            type: string
                            nullable: true
                          last_name:
                            type: string
                            nullable: true
                          email:
                            type: string
                            nullable: true
                          profile_picture:
                            type: string
                            nullable: true
                      usage:
                        type: object
                        description: >-
                          Per-session usage totals. Credit and token counts
                          accumulate as the agent runs and are `null` until the
                          first run records usage.
                        properties:
                          credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Total credits consumed by the session, including
                              tool and flow (workflow) credits.
                            example: 12.5
                          tool_credit_cost:
                            type: number
                            nullable: true
                            description: Credits consumed by tool calls.
                            example: 4
                          flow_credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Credits consumed by workflow (flow) runs invoked
                              by the agent.
                            example: 1.5
                          input_tokens:
                            type: integer
                            nullable: true
                            description: Total input tokens across the session.
                            example: 18432
                          output_tokens:
                            type: integer
                            nullable: true
                            description: Total output tokens across the session.
                            example: 2043
                  queue_position:
                    type: integer
                    nullable: true
                    description: >-
                      Position in the per-agent queue. Populated only when the
                      session was queued; otherwise `null`.
                    example: null
              examples:
                idle:
                  summary: Idle session created (no input)
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages: []
                      created_at: '2026-05-15T14:32:00Z'
                      state: idle
                      agent_name: Sales research agent
                      agent_team_id: team_4f8c92ab
                      agent_creator_user_id: user_2b9d71f0
                      agent_icon_url: null
                      agent_tools: []
                      participants: {}
                      creator:
                        id: user_2b9d71f0
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
                      usage:
                        credit_cost: null
                        tool_credit_cost: null
                        flow_credit_cost: null
                        input_tokens: null
                        output_tokens: null
                    queue_position: null
        '202':
          description: >-
            Session created and the first message was enqueued. Returned when
            `input` is provided. `queue_position` is set only when the session
            was queued behind concurrent runs.
          content:
            application/json:
              schema:
                type: object
                properties:
                  session:
                    type: object
                    properties:
                      id:
                        type: string
                        example: sess_xYz789AbCd
                      agent_id:
                        type: string
                        example: abc123DEFghiJKL
                      messages:
                        type: array
                        items:
                          type: object
                          properties:
                            id:
                              type: string
                              nullable: true
                              example: msg_a1b2c3
                            role:
                              type: string
                              nullable: true
                              example: user
                            content:
                              type: string
                              nullable: true
                              example: Research Acme Corp and draft a brief.
                            created_at:
                              type: string
                              format: date-time
                              nullable: true
                              example: '2026-05-15T14:32:00Z'
                            creator_id:
                              type: string
                              nullable: true
                              example: user_2b9d71f0
                            parts:
                              type: array
                              nullable: true
                              items:
                                type: object
                      created_at:
                        type: string
                        format: date-time
                        nullable: true
                        example: '2026-05-15T14:32:00Z'
                      state:
                        type: string
                        nullable: true
                        enum:
                          - processing
                          - completed
                          - failed
                          - queued
                          - idle
                          - approval_required
                        example: processing
                      agent_name:
                        type: string
                        nullable: true
                        example: Sales research agent
                      agent_team_id:
                        type: string
                        nullable: true
                        example: team_4f8c92ab
                      agent_creator_user_id:
                        type: string
                        nullable: true
                        example: user_2b9d71f0
                      agent_icon_url:
                        type: string
                        nullable: true
                        example: null
                      agent_tools:
                        type: array
                        description: >-
                          Tools available to the agent. Secret references are
                          stripped before being returned.
                        items:
                          type: object
                      participants:
                        type: object
                        description: Map of participant user IDs to participant metadata.
                      creator:
                        type: object
                        nullable: true
                        description: >-
                          Creator of the session. `null` when no creator is
                          recorded.
                        properties:
                          id:
                            type: string
                            nullable: true
                          first_name:
                            type: string
                            nullable: true
                          last_name:
                            type: string
                            nullable: true
                          email:
                            type: string
                            nullable: true
                          profile_picture:
                            type: string
                            nullable: true
                      usage:
                        type: object
                        description: >-
                          Per-session usage totals. Credit and token counts
                          accumulate as the agent runs and are `null` until the
                          first run records usage.
                        properties:
                          credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Total credits consumed by the session, including
                              tool and flow (workflow) credits.
                            example: 12.5
                          tool_credit_cost:
                            type: number
                            nullable: true
                            description: Credits consumed by tool calls.
                            example: 4
                          flow_credit_cost:
                            type: number
                            nullable: true
                            description: >-
                              Credits consumed by workflow (flow) runs invoked
                              by the agent.
                            example: 1.5
                          input_tokens:
                            type: integer
                            nullable: true
                            description: Total input tokens across the session.
                            example: 18432
                          output_tokens:
                            type: integer
                            nullable: true
                            description: Total output tokens across the session.
                            example: 2043
                  queue_position:
                    type: integer
                    nullable: true
                    description: >-
                      Position in the per-agent queue. Populated only when the
                      session was queued; otherwise `null`.
                    example: null
              examples:
                processing:
                  summary: Message enqueued and processing
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages:
                        - id: msg_a1b2c3
                          role: user
                          content: Research Acme Corp and draft a brief.
                          created_at: '2026-05-15T14:32:00Z'
                          creator_id: user_2b9d71f0
                          parts: null
                      created_at: '2026-05-15T14:32:00Z'
                      state: processing
                      agent_name: Sales research agent
                      agent_team_id: team_4f8c92ab
                      agent_creator_user_id: user_2b9d71f0
                      agent_icon_url: null
                      agent_tools: []
                      participants:
                        user_2b9d71f0:
                          first_name: Ada
                          last_name: Lovelace
                      creator:
                        id: user_2b9d71f0
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
                    queue_position: null
                queued:
                  summary: Message queued behind concurrent runs
                  value:
                    session:
                      id: sess_xYz789AbCd
                      agent_id: abc123DEFghiJKL
                      messages:
                        - id: msg_a1b2c3
                          role: user
                          content: Research Acme Corp and draft a brief.
                          created_at: '2026-05-15T14:32:00Z'
                          creator_id: user_2b9d71f0
                          parts: null
                      created_at: '2026-05-15T14:32:00Z'
                      state: queued
                      agent_name: Sales research agent
                      agent_team_id: team_4f8c92ab
                      agent_creator_user_id: user_2b9d71f0
                      agent_icon_url: null
                      agent_tools: []
                      participants:
                        user_2b9d71f0:
                          first_name: Ada
                          last_name: Lovelace
                      creator:
                        id: user_2b9d71f0
                        first_name: Ada
                        last_name: Lovelace
                        email: ada@example.com
                        profile_picture: null
                    queue_position: 3
        '400':
          description: >-
            Bad request — invalid body, or `stream: true` was set on this host
            (use the streaming host).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have read access on the agent.
        '404':
          description: Agent not found.
        '409':
          description: Conflict — a session with the supplied `session_id` already exists.
        '429':
          description: Rate limited — the agent's concurrency limit was exceeded.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/sessions' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{"input": "Research Acme Corp and draft a brief."}'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.sessions.create(
                "abc123DEFghiJKL",
                input="Research Acme Corp and draft a brief.",
            )
            print(response.session.id, response.session.state)
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````