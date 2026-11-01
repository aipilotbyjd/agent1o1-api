> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Resolve approvals

> Answer pending asks on a session that is paused in the `approval_required` state — tool approvals, human input requests, and checkpoints.

List the pending asks with Retrieve session: each entry in `pending_approvals` carries the `action_request_id` to answer, and `human_input` asks include the `questions` to fill in via `response.values`. Resolutions are processed in order; the agent resumes once the pending asks are answered.




## OpenAPI

````yaml post /sessions/{session_id}/approvals
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/approvals:
    post:
      tags:
        - Sessions
      summary: Resolve approvals
      description: >
        Answer pending asks on a session that is paused in the
        `approval_required` state — tool approvals, human input requests, and
        checkpoints.


        List the pending asks with Retrieve session: each entry in
        `pending_approvals` carries the `action_request_id` to answer, and
        `human_input` asks include the `questions` to fill in via
        `response.values`. Resolutions are processed in order; the agent resumes
        once the pending asks are answered.
      operationId: resolveSessionApprovals
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session with pending approvals.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - approval_responses
              properties:
                approval_responses:
                  type: array
                  minItems: 1
                  maxItems: 20
                  description: >-
                    Answers to pending asks. Each `action_request_id` may appear
                    at most once.
                  items:
                    type: object
                    required:
                      - action_request_id
                      - action
                    properties:
                      action_request_id:
                        type: string
                        description: >-
                          ID of the pending ask, from `pending_approvals` on
                          Retrieve session.
                        example: areq_9f3k2m
                      action:
                        type: string
                        enum:
                          - accept
                          - reject
                        description: Whether to approve or reject the pending ask.
                      reason:
                        type: string
                        maxLength: 1000
                        description: Optional reason recorded with the resolution.
                      response:
                        type: object
                        description: >-
                          For `human_input` asks — form answers keyed by
                          question name.
                        properties:
                          values:
                            type: object
                            description: Map of question name to answer.
                            example:
                              email_subject: Q3 pipeline review
      responses:
        '200':
          description: >-
            Resolutions applied. `results` reports the outcome per ask;
            `session` reflects the state after resolution.
          content:
            application/json:
              schema:
                type: object
                properties:
                  session:
                    type: object
                    description: >-
                      The session after resolution — same shape as Retrieve
                      session.
                  results:
                    type: array
                    items:
                      type: object
                      properties:
                        action_request_id:
                          type: string
                          example: areq_9f3k2m
                        action:
                          type: string
                          enum:
                            - accept
                            - reject
                          example: accept
                        outcome:
                          type: string
                          description: >-
                            Resolution outcome code — `accepted` or `rejected`
                            for fresh resolutions, `already_accepted` /
                            `already_rejected` when the ask was already resolved
                            the same way.
                          example: accepted
                  stream_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Cursor to resume the session's event stream from, when the
                      resolution restarted the agent.
              example:
                session:
                  id: sess_xYz789AbCd
                  agent_id: abc123DEFghiJKL
                  state: processing
                  pending_approvals: []
                results:
                  - action_request_id: areq_9f3k2m
                    action: accept
                    outcome: accepted
                stream_cursor: null
        '400':
          description: >-
            Bad request — unknown or already-resolved `action_request_id`, a
            repeated `action_request_id`, or an invalid `human_input` answer.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session not found.
        '409':
          description: >-
            Conflict — the session has no pending approvals
            (`session_not_awaiting_approval`).
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/approvals'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "approval_responses": [
                  {"action_request_id": "areq_9f3k2m", "action": "accept"}
                ]
              }'
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