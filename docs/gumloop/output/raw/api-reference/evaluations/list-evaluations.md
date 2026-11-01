> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List evaluations

> Returns a cursor-paginated list of evaluation results for a specific agent, newest first.
Only completed and failed evaluations are returned (transient states like queued/in_progress are excluded).

Each evaluation includes the grade, criteria pass/fail results, extracted data points, applied tags, and sentiment analysis.




## OpenAPI

````yaml get /agents/{agent_id}/evaluations
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/evaluations:
    get:
      tags:
        - Evaluations
      summary: List evaluations
      description: >
        Returns a cursor-paginated list of evaluation results for a specific
        agent, newest first.

        Only completed and failed evaluations are returned (transient states
        like queued/in_progress are excluded).


        Each evaluation includes the grade, criteria pass/fail results,
        extracted data points, applied tags, and sentiment analysis.
      operationId: listEvaluations
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent whose evaluations to list. Also accepts the reserved
            aliases `gumball` and `analytics`.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
          description: Number of evaluations to return per page (1-100).
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: Pagination cursor from a previous response's `next_cursor` field.
        - in: query
          name: grade
          required: false
          schema:
            type: string
            enum:
              - pass
              - needs_review
              - needs_attention
          description: Filter evaluations by grade.
      responses:
        '200':
          description: Paginated list of evaluation results.
          content:
            application/json:
              schema:
                type: object
                properties:
                  evaluations:
                    type: array
                    items:
                      $ref: '#/components/schemas/EvaluationResult'
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Pass this value as the `cursor` query parameter to fetch
                      the next page. Null when there are no more results.
              example:
                evaluations:
                  - evaluation_id: eval_abc123
                    interaction_id: int_xyz789
                    agent_id: agent_456
                    created_ts: '2026-06-15T14:30:00+00:00'
                    status: completed
                    grade: pass
                    call_successful: success
                    sentiment: positive
                    summary: >-
                      User asked about pricing and the agent provided accurate
                      tier information.
                    criteria_results:
                      - id: crit_1
                        name: Accuracy
                        type: other
                        priority: needs_attention
                        result: success
                        rationale: All pricing information matched the current rate card.
                      - id: crit_2
                        name: Stayed on Topic
                        type: voice_tone
                        priority: needs_review
                        result: success
                        rationale: Agent focused exclusively on the pricing question.
                    data_results:
                      - id: dp_1
                        name: Customer Intent
                        data_type: string
                        value: pricing inquiry
                      - id: dp_2
                        name: Confidence Score
                        data_type: number
                        value: 8.5
                    applied_tags:
                      - PRICING_INQUIRY
                      - POSITIVE_FEEDBACK
                next_cursor: eyJjcmVhdGVkX3RzIjoiMjAyNi0wNi0xNVQxNDozMDowMCswMDowMCJ9
        '400':
          description: Invalid grade parameter.
        '401':
          description: Unauthorized — missing or invalid credentials.
        '403':
          description: Forbidden — insufficient permissions on this agent.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations?page_size=20'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            import requests

            response = requests.get(
                "https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations",
                headers={"Authorization": "Bearer YOUR_ACCESS_TOKEN"},
                params={"page_size": 20}
            )
            data = response.json()
            for evaluation in data["evaluations"]:
                print(evaluation["grade"], evaluation["data_results"])
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