> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Update evaluation config

> Partially update the evaluation configuration for an agent.
Omitted fields keep their current value. Provided list fields (criteria, tags, data_points) replace that list entirely.

Requires Pro tier or above.




## OpenAPI

````yaml patch /agents/{agent_id}/evaluation-config
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/evaluation-config:
    patch:
      tags:
        - Evaluations
      summary: Update evaluation config
      description: >
        Partially update the evaluation configuration for an agent.

        Omitted fields keep their current value. Provided list fields (criteria,
        tags, data_points) replace that list entirely.


        Requires Pro tier or above.
      operationId: updateEvaluationConfig
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent. Also accepts the reserved aliases `gumball` and
            `analytics`.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                enabled:
                  type: boolean
                  description: Whether evaluations are enabled for this agent.
                model_name:
                  type: string
                  description: LLM model to use for evaluation.
                include_auto_tags:
                  type: boolean
                  description: >-
                    Allow the evaluator to suggest tags beyond your predefined
                    vocabulary.
                criteria:
                  type: array
                  description: Quality criteria to check (replaces existing list). Max 30.
                  items:
                    type: object
                    properties:
                      name:
                        type: string
                      prompt:
                        type: string
                        description: True/false statement to evaluate.
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
                tags:
                  type: array
                  description: Tag vocabulary (replaces existing list). Max 50.
                  items:
                    type: object
                    properties:
                      name:
                        type: string
                      description:
                        type: string
                data_points:
                  type: array
                  description: Data points to extract (replaces existing list). Max 40.
                  items:
                    type: object
                    properties:
                      name:
                        type: string
                      data_type:
                        type: string
                        enum:
                          - string
                          - boolean
                          - integer
                          - number
                      description:
                        type: string
                sentiment:
                  type: object
                  properties:
                    enabled:
                      type: boolean
                    affects_grade:
                      type: boolean
                    description:
                      type: string
      responses:
        '200':
          description: Updated evaluation configuration.
          content:
            application/json:
              schema:
                type: object
                properties:
                  config:
                    $ref: '#/components/schemas/EvaluationConfig'
        '400':
          description: Invalid request (e.g. exceeds limits).
        '401':
          description: Unauthorized — missing or invalid credentials.
        '403':
          description: Forbidden — requires Pro tier or insufficient permissions.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X PATCH
            'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluation-config' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "enabled": true,
                "criteria": [
                  {
                    "name": "Accuracy",
                    "prompt": "The agent provided factually correct information.",
                    "type": "other",
                    "priority": "needs_attention"
                  }
                ],
                "data_points": [
                  {
                    "name": "Customer Intent",
                    "data_type": "string",
                    "description": "Summarize the customer primary intent in 2-5 words."
                  }
                ]
              }'
        - lang: python
          label: Python
          source: |
            import requests

            response = requests.patch(
                "https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluation-config",
                headers={
                    "Authorization": "Bearer YOUR_ACCESS_TOKEN",
                    "Content-Type": "application/json"
                },
                json={
                    "enabled": True,
                    "criteria": [
                        {
                            "name": "Accuracy",
                            "prompt": "The agent provided factually correct information.",
                            "type": "other",
                            "priority": "needs_attention"
                        }
                    ]
                }
            )
            config = response.json()["config"]
components:
  schemas:
    EvaluationConfig:
      type: object
      properties:
        agent_id:
          type: string
        enabled:
          type: boolean
        is_active:
          type: boolean
        model_name:
          type: string
          nullable: true
        frequency:
          type: string
          nullable: true
        language:
          type: string
          nullable: true
        include_auto_tags:
          type: boolean
        interaction_types:
          type: array
          items:
            type: string
        criteria:
          type: array
          items:
            type: object
            properties:
              id:
                type: string
              name:
                type: string
              prompt:
                type: string
              type:
                type: string
              priority:
                type: string
        tags:
          type: array
          items:
            type: object
            properties:
              name:
                type: string
              description:
                type: string
        data_points:
          type: array
          items:
            type: object
            properties:
              id:
                type: string
              name:
                type: string
              data_type:
                type: string
              description:
                type: string
        sentiment:
          type: object
          nullable: true
          properties:
            enabled:
              type: boolean
            affects_grade:
              type: boolean
            description:
              type: string
        updated_ts:
          type: string
          nullable: true
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````