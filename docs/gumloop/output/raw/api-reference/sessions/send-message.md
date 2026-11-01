> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Send message

> Append a user message to an existing session and resume the agent. The session must be in a terminal state (`idle`, `completed`, or `failed`); sending to a session that is `processing` or `queued` returns `409 interaction_not_in_terminal_state`. To hand the agent a message while it is still busy, use the [message queue](/api-reference/sessions/queue-message) instead.

Files uploaded via [Upload session file](/api-reference/sessions/upload-session-file) can be attached to the message with `attachments`.

If the session is `approval_required`, answer the pending asks via [Resolve approvals](/api-reference/sessions/resolve-approvals) — this endpoint rejects `approval_responses` with a `400`.

### Streaming the response

`api.gumloop.com` only serves the non-streaming response above. To stream agent output as it's produced, send the same request body (with `stream: true`) to the streaming host instead:

```
POST https://ws.gumloop.com/api/v1/sessions/{session_id}/messages
```

The response is `text/event-stream` (Server-Sent Events). With the Python SDK, `client.sessions.stream_message(session_id, input="...")` routes to `ws.gumloop.com` automatically and yields parsed `StreamEvent` objects.

If you send `stream: true` to `api.gumloop.com` by mistake, the response is a `400` whose body contains the correct streaming host so you can retry against it.




## OpenAPI

````yaml post /sessions/{session_id}/messages
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/messages:
    post:
      tags:
        - Sessions
      summary: Send message
      description: >
        Append a user message to an existing session and resume the agent. The
        session must be in a terminal state (`idle`, `completed`, or `failed`);
        sending to a session that is `processing` or `queued` returns `409
        interaction_not_in_terminal_state`. To hand the agent a message while it
        is still busy, use the [message
        queue](/api-reference/sessions/queue-message) instead.


        Files uploaded via [Upload session
        file](/api-reference/sessions/upload-session-file) can be attached to
        the message with `attachments`.


        If the session is `approval_required`, answer the pending asks via
        [Resolve approvals](/api-reference/sessions/resolve-approvals) — this
        endpoint rejects `approval_responses` with a `400`.


        ### Streaming the response


        `api.gumloop.com` only serves the non-streaming response above. To
        stream agent output as it's produced, send the same request body (with
        `stream: true`) to the streaming host instead:


        ```

        POST https://ws.gumloop.com/api/v1/sessions/{session_id}/messages

        ```


        The response is `text/event-stream` (Server-Sent Events). With the
        Python SDK, `client.sessions.stream_message(session_id, input="...")`
        routes to `ws.gumloop.com` automatically and yields parsed `StreamEvent`
        objects.


        If you send `stream: true` to `api.gumloop.com` by mistake, the response
        is a `400` whose body contains the correct streaming host so you can
        retry against it.
      operationId: sendMessage
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session to continue.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                input:
                  type: string
                  description: >-
                    The next user message. Required. Also accepted as `message`
                    for backwards compatibility.
                  example: Now write a follow-up email.
                stream:
                  type: boolean
                  default: false
                  description: >-
                    Must be `false` (or omitted) when calling `api.gumloop.com`.
                    Set to `true` only when calling `ws.gumloop.com` (see the
                    streaming section above).
                attachments:
                  type: array
                  maxItems: 10
                  description: >-
                    Files to attach to the message. Each `file_name` must be a
                    stored path returned by Upload session file for this
                    session.
                  items:
                    type: object
                    required:
                      - file_name
                    properties:
                      file_name:
                        type: string
                        description: Stored path returned by Upload session file.
                        example: >-
                          custom_agent_interactions/sess_xYz789AbCd/input/report.pdf
                      media_type:
                        type: string
                        nullable: true
                        description: MIME type of the file.
                        example: application/pdf
              required:
                - input
      responses:
        '202':
          description: >-
            Message was enqueued. `queue_position` is set only when the session
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
                              example: msg_g7h8i9
                            role:
                              type: string
                              nullable: true
                              example: user
                            content:
                              type: string
                              nullable: true
                              example: Now write a follow-up email.
                            created_at:
                              type: string
                              format: date-time
                              nullable: true
                              example: '2026-05-15T14:35:00Z'
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
                  summary: Continuation enqueued and processing
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
                        - id: msg_d4e5f6
                          role: assistant
                          content: Here is what I found about Acme Corp...
                          created_at: '2026-05-15T14:32:09Z'
                          creator_id: null
                          parts: null
                        - id: msg_g7h8i9
                          role: user
                          content: Now write a follow-up email.
                          created_at: '2026-05-15T14:35:00Z'
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
        '400':
          description: >-
            Bad request — missing `input`, `stream: true` was set on this host
            (use the streaming host), `approval_responses` was included
            (`approvals_require_stream_or_approvals_endpoint` — use the
            approvals endpoint), or an attachment path is outside this session's
            input namespace (`invalid_attachment`).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session not found.
        '409':
          description: >-
            Conflict — the session is not in a terminal state
            (`interaction_not_in_terminal_state`).
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
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/messages' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{"input": "Now write a follow-up email."}'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.sessions.send(
                "sess_xYz789AbCd",
                input="Now write a follow-up email.",
            )
            print(response.session.state)
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