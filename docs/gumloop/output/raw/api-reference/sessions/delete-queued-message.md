> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Delete queued message

> Remove a message from the session's queue before it is sent.



## OpenAPI

````yaml delete /sessions/{session_id}/queue/{queued_message_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/queue/{queued_message_id}:
    delete:
      tags:
        - Sessions
      summary: Delete queued message
      description: Remove a message from the session's queue before it is sent.
      operationId: deleteQueuedMessage
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
          description: ID of the queued message to delete.
      responses:
        '200':
          description: Queued message removed. `queue` is the remaining queue.
          content:
            application/json:
              schema:
                type: object
                properties:
                  queue:
                    type: array
                    items:
                      $ref: '#/components/schemas/QueuedMessage'
              example:
                queue: []
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session or queued message not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X DELETE
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/queue/qmsg_1a2b3c'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
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