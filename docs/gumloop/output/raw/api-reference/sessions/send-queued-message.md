> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Send queued message now

> Send a queued message immediately instead of waiting for the agent to finish its current turn. Any in-progress run is aborted, the queued message is appended to the transcript, and the agent starts processing it.

The response is the same envelope as Send message. Queued messages cannot be sent this way on incognito sessions, and a message that is currently being edited must have its edit finished or cancelled first.




## OpenAPI

````yaml post /sessions/{session_id}/queue/{queued_message_id}/send
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/queue/{queued_message_id}/send:
    post:
      tags:
        - Sessions
      summary: Send queued message now
      description: >
        Send a queued message immediately instead of waiting for the agent to
        finish its current turn. Any in-progress run is aborted, the queued
        message is appended to the transcript, and the agent starts processing
        it.


        The response is the same envelope as Send message. Queued messages
        cannot be sent this way on incognito sessions, and a message that is
        currently being edited must have its edit finished or cancelled first.
      operationId: sendQueuedMessage
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session the queued message belongs to.
        - in: path
          name: queued_message_id
          required: true
          schema:
            type: string
          description: ID of the queued message to send.
      responses:
        '202':
          description: >-
            Message sent and processing started. Same envelope as Send message —
            `session` plus `queue_position`.
          content:
            application/json:
              schema:
                type: object
                properties:
                  session:
                    type: object
                    description: >-
                      The session after the message was promoted — same shape as
                      Retrieve session.
                  queue_position:
                    type: integer
                    nullable: true
                    description: >-
                      Position in the per-agent queue. Populated only when the
                      session was queued behind concurrent runs; otherwise
                      `null`.
              example:
                session:
                  id: sess_xYz789AbCd
                  agent_id: abc123DEFghiJKL
                  state: processing
                queue_position: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session, agent, or queued message not found.
        '409':
          description: >-
            Conflict — the session is incognito
            (`incognito_session_unsupported`), or the queued message is mid-edit
            (`queued_message_not_eligible`).
        '429':
          description: Rate limited — the agent's concurrency limit was exceeded.
        '500':
          description: >-
            Internal server error — the promoted message could not be persisted
            (`promoted_message_persist_failed`).
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/queue/qmsg_1a2b3c/send'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
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