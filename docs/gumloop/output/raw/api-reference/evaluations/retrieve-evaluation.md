> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve evaluation

> Retrieve a single evaluation result by ID. The evaluation must belong to the specified agent.



## OpenAPI

````yaml get /agents/{agent_id}/evaluations/{evaluation_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/evaluations/{evaluation_id}:
    get:
      tags:
        - Evaluations
      summary: Retrieve evaluation
      description: >-
        Retrieve a single evaluation result by ID. The evaluation must belong to
        the specified agent.
      operationId: retrieveEvaluation
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent the evaluation belongs to. Also accepts the reserved
            aliases `gumball` and `analytics`.
        - in: path
          name: evaluation_id
          required: true
          schema:
            type: string
          description: ID of the evaluation to retrieve.
      responses:
        '200':
          description: The requested evaluation result.
          content:
            application/json:
              schema:
                type: object
                properties:
                  evaluation:
                    $ref: '#/components/schemas/EvaluationResult'
              example:
                evaluation:
                  evaluation_id: eval_abc123
                  interaction_id: int_xyz789
                  agent_id: agent_456
                  created_ts: '2026-06-15T14:30:00+00:00'
                  status: completed
                  grade: needs_review
                  call_successful: success
                  sentiment: negative
                  summary: >-
                    User was frustrated with response time. Agent eventually
                    resolved the issue.
                  criteria_results:
                    - id: crit_1
                      name: Accuracy
                      type: other
                      priority: needs_attention
                      result: success
                      rationale: Information provided was correct.
                    - id: crit_2
                      name: Professional Tone
                      type: voice_tone
                      priority: needs_review
                      result: failure
                      rationale: >-
                        Agent used overly casual language in a formal support
                        context.
                  data_results:
                    - id: dp_1
                      name: Resolution Status
                      data_type: string
                      value: resolved
                    - id: dp_2
                      name: Handoff Requested
                      data_type: boolean
                      value: false
                  applied_tags:
                    - SUPPORT_TICKET
                    - TONE_ISSUE
        '401':
          description: Unauthorized — missing or invalid credentials.
        '404':
          description: Evaluation not found or does not belong to this agent.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations/EVALUATION_ID'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            import requests

            response = requests.get(
                "https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations/EVALUATION_ID",
                headers={"Authorization": "Bearer YOUR_ACCESS_TOKEN"}
            )
            evaluation = response.json()["evaluation"]
            print(evaluation["grade"], evaluation["summary"])
components:
  schemas:
    EvaluationResult:
      type: object
      properties:
        evaluation_id:
          type: string
          description: Unique ID of this evaluation.
        interaction_id:
          type: string
          description: ID of the interaction that was evaluated.
        agent_id:
          type: string
          description: ID of the agent.
        created_ts:
          type: string
          format: date-time
          description: When the evaluation was created.
        status:
          type: string
          enum:
            - completed
            - failed
          description: Terminal status of the evaluation.
        grade:
          type: string
          enum:
            - pass
            - needs_review
            - needs_attention
          description: Overall grade computed from criteria, sentiment, and call outcome.
        call_successful:
          type: string
          enum:
            - success
            - failure
            - unknown
          description: Whether the agent's call/task was successful.
        sentiment:
          type: string
          nullable: true
          enum:
            - positive
            - neutral
            - negative
          description: >-
            Detected sentiment of the interaction (null if sentiment analysis is
            disabled).
        summary:
          type: string
          nullable: true
          description: One or two sentence narrative of what happened in the interaction.
        criteria_results:
          type: array
          description: Per-criterion pass/fail results with rationales.
          items:
            type: object
            properties:
              id:
                type: string
              name:
                type: string
              type:
                type: string
                enum:
                  - prohibited_action
                  - prohibited_words
                  - voice_tone
                  - other
              priority:
                type: string
                enum:
                  - needs_review
                  - needs_attention
              result:
                type: string
                enum:
                  - success
                  - failure
                  - unknown
              rationale:
                type: string
        data_results:
          type: array
          description: Extracted data point values from the conversation.
          items:
            type: object
            properties:
              id:
                type: string
              name:
                type: string
              data_type:
                type: string
                enum:
                  - string
                  - boolean
                  - integer
                  - number
              value:
                description: >-
                  The extracted value. Type depends on data_type. Null if the
                  evaluator couldn't find the information.
                nullable: true
        applied_tags:
          type: array
          description: Tags applied to this evaluation.
          items:
            type: string
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````