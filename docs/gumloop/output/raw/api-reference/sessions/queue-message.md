> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Queue message

> Add a message to a session's queue instead of interrupting the agent. Queued messages are sent automatically, in order, when the agent finishes its current turn. A session's queue holds at most 20 messages.

To interrupt the current turn and send a queued message immediately, use [Send queued message now](/api-reference/sessions/send-queued-message).




## OpenAPI

````yaml post /sessions/{session_id}/queue
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/queue:
    post:
      tags:
        - Sessions
      summary: Queue message
      description: >
        Add a message to a session's queue instead of interrupting the agent.
        Queued messages are sent automatically, in order, when the agent
        finishes its current turn. A session's queue holds at most 20 messages.


        To interrupt the current turn and send a queued message immediately, use
        [Send queued message now](/api-reference/sessions/send-queued-message).
      operationId: queueSessionMessage
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session to queue the message on.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - input
              properties:
                input:
                  type: string
                  description: >-
                    The message to queue. Cannot be empty. Also accepted as
                    `message` for backwards compatibility.
                  example: Also check their latest funding round.
      responses:
        '201':
          description: >-
            Message queued. `queued_message` is the new entry; `queue` is the
            rest of the queue after it.
          content:
            application/json:
              schema:
                type: object
                properties:
                  queued_message:
                    $ref: '#/components/schemas/QueuedMessage'
                  queue:
                    type: array
                    items:
                      $ref: '#/components/schemas/QueuedMessage'
              example:
                queued_message:
                  id: qmsg_1a2b3c
                  input: Also check their latest funding round.
                  state: queued
                  position: 1
                  created_at: '2026-05-15T14:36:00Z'
                  updated_at: '2026-05-15T14:36:00Z'
                queue: []
        '400':
          description: Bad request — empty `input`.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session not found.
        '409':
          description: Conflict — the queue already holds 20 messages (`queue_full`).
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/queue' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{"input": "Also check their latest funding round."}'
components:
  schemas:
    QueuedMessage:
      type: object
      properties:
        id:
          type: string
          description: Unique ID of the queued message.
          example: qmsg_1a2b3c
        input:
          type: string
          nullable: true
          description: The message content.
          example: Also check their latest funding round.
        state:
          type: string
          enum:
            - queued
            - editing
          description: >-
            `queued` messages are eligible to send; `editing` messages are
            mid-edit and skipped until the edit finishes.
          example: queued
        position:
          type: integer
          description: 1-based position in the queue.
          example: 1
        created_at:
          type: string
          format: date-time
          nullable: true
          example: '2026-05-15T14:36:00Z'
        updated_at:
          type: string
          format: date-time
          nullable: true
          example: '2026-05-15T14:36:00Z'
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````