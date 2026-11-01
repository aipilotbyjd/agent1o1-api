> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Get evaluation config

> Retrieve the current evaluation configuration for an agent, including criteria, tags, data points, and sentiment settings.



## OpenAPI

````yaml get /agents/{agent_id}/evaluation-config
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/evaluation-config:
    get:
      tags:
        - Evaluations
      summary: Get evaluation config
      description: >-
        Retrieve the current evaluation configuration for an agent, including
        criteria, tags, data points, and sentiment settings.
      operationId: getEvaluationConfig
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent. Also accepts the reserved aliases `gumball` and
            `analytics`.
      responses:
        '200':
          description: The agent's evaluation configuration.
          content:
            application/json:
              schema:
                type: object
                properties:
                  config:
                    $ref: '#/components/schemas/EvaluationConfig'
              example:
                config:
                  agent_id: agent_456
                  enabled: true
                  is_active: true
                  model_name: anthropic/claude-sonnet-4
                  frequency: debounced
                  language: auto
                  include_auto_tags: true
                  interaction_types: []
                  criteria:
                    - id: crit_1
                      name: Accuracy
                      prompt: The agent provided factually correct information.
                      type: other
                      priority: needs_attention
                    - id: crit_2
                      name: Professional Tone
                      prompt: The agent maintained a professional tone throughout.
                      type: voice_tone
                      priority: needs_review
                  tags:
                    - name: PRICING_INQUIRY
                      description: User asked about pricing or billing.
                    - name: ESCALATION_NEEDED
                      description: Issue requires human intervention.
                  data_points:
                    - id: dp_1
                      name: Customer Intent
                      data_type: string
                      description: Summarize the customer's primary intent in 2-5 words.
                    - id: dp_2
                      name: Confidence Score
                      data_type: number
                      description: Rate 1-10 how confident the agent appeared.
                  sentiment:
                    enabled: true
                    affects_grade: true
                    description: Focus on the customer's final message tone.
                  updated_ts: '2026-06-10T09:00:00+00:00'
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
            'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluation-config' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            import requests


            response = requests.get(
                "https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluation-config",
                headers={"Authorization": "Bearer YOUR_ACCESS_TOKEN"}
            )

            config = response.json()["config"]

            print(f"Enabled: {config['enabled']}, Criteria:
            {len(config['criteria'])}")
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