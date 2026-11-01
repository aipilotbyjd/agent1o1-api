> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Get evaluation metrics

> Returns aggregated grade and tag counts for an agent's evaluations over a time window.
Useful for dashboards and reporting on agent quality trends.




## OpenAPI

````yaml get /agents/{agent_id}/evaluations/metrics
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/evaluations/metrics:
    get:
      tags:
        - Evaluations
      summary: Get evaluation metrics
      description: >
        Returns aggregated grade and tag counts for an agent's evaluations over
        a time window.

        Useful for dashboards and reporting on agent quality trends.
      operationId: getEvaluationMetrics
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent. Also accepts the reserved aliases `gumball` and
            `analytics`.
        - in: query
          name: days
          required: false
          schema:
            type: integer
            default: 30
            minimum: 1
            maximum: 365
          description: Number of days to look back (1-365). Defaults to 30.
      responses:
        '200':
          description: Aggregated evaluation metrics.
          content:
            application/json:
              schema:
                type: object
                properties:
                  grades:
                    type: object
                    description: Count of evaluations per grade.
                    properties:
                      pass:
                        type: integer
                      needs_review:
                        type: integer
                      needs_attention:
                        type: integer
                  tags:
                    type: object
                    description: Count of evaluations per applied tag.
                    additionalProperties:
                      type: integer
              example:
                grades:
                  pass: 118
                  needs_review: 19
                  needs_attention: 5
                tags:
                  PRICING_INQUIRY: 34
                  SUPPORT_TICKET: 52
                  ESCALATION_NEEDED: 8
                  POSITIVE_FEEDBACK: 45
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
            'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations/metrics?days=30'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            import requests

            response = requests.get(
                "https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations/metrics",
                headers={"Authorization": "Bearer YOUR_ACCESS_TOKEN"},
                params={"days": 30}
            )
            metrics = response.json()
            print(f"Pass rate: {metrics['grades'].get('pass', 0)}")
            print(f"Tags: {metrics['tags']}")
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