> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Call MCP tools

> Execute a batch of 1–5 MCP tool calls. Calls run concurrently and each result reports its own `status`. When Gumloop accepts the request, MCP execution failures such as target server authentication, policy blocks, invalid tools, upstream HTTP errors, and connection failures are returned in `results[*].status` and `results[*].error`. Top-level `4xx` responses are reserved for Gumloop request, authentication, and permission failures. `200` covers homogeneous execution outcomes (all calls succeeded or all calls failed); mixed success/failure batches return `207`. If you previously treated non-2xx HTTP statuses as MCP execution failures, update your integration to inspect each result's `status` and `error`.



## OpenAPI

````yaml post /mcp/tools/call
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /mcp/tools/call:
    post:
      tags:
        - MCP
      summary: Call MCP tools
      description: >-
        Execute a batch of 1–5 MCP tool calls. Calls run concurrently and each
        result reports its own `status`. When Gumloop accepts the request, MCP
        execution failures such as target server authentication, policy blocks,
        invalid tools, upstream HTTP errors, and connection failures are
        returned in `results[*].status` and `results[*].error`. Top-level `4xx`
        responses are reserved for Gumloop request, authentication, and
        permission failures. `200` covers homogeneous execution outcomes (all
        calls succeeded or all calls failed); mixed success/failure batches
        return `207`. If you previously treated non-2xx HTTP statuses as MCP
        execution failures, update your integration to inspect each result's
        `status` and `error`.
      operationId: callMcpTools
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - calls
              properties:
                calls:
                  type: array
                  minItems: 1
                  maxItems: 5
                  description: >-
                    Tool calls to execute. Dispatched concurrently; the batch is
                    capped at 5.
                  items:
                    type: object
                    required:
                      - server_id
                      - tool_name
                    properties:
                      ref:
                        type: string
                        nullable: true
                        description: >-
                          Caller-supplied identifier echoed back on the matching
                          result. When omitted, Gumloop assigns the call's
                          zero-based index in `calls` as its `ref`.
                        example: send-1
                      server_id:
                        type: string
                        example: gumloop_slack
                      tool_name:
                        type: string
                        example: slack_send_message
                      arguments:
                        type: object
                        description: >-
                          Arguments passed to the tool. Defaults to `{}`.
                          Validated by the tool's `input_schema`.
                        example:
                          channel: '#general'
                          text: Hello from Gumloop
                team_id:
                  type: string
                  nullable: true
                  description: Team the calls are scoped to.
                  example: team_4f8c92ab
      responses:
        '200':
          description: >-
            Batch processed. Inspect each result's `status` and `error`; this
            can include all-success and all-failed execution outcomes.
          content:
            application/json:
              schema:
                type: object
                properties:
                  results:
                    type: array
                    items:
                      type: object
                      required:
                        - ref
                        - status
                      properties:
                        ref:
                          type: string
                          example: send-1
                        server_id:
                          type: string
                          nullable: true
                          example: gumloop_slack
                        tool_name:
                          type: string
                          nullable: true
                          example: slack_send_message
                        status:
                          type: string
                          description: One of `success`, `unauthenticated`, or `error`.
                          example: success
                        content:
                          type: array
                          nullable: true
                          description: >-
                            Raw MCP content blocks returned by the tool when
                            `status` is `success`.
                          items:
                            type: object
                          example:
                            - type: text
                              text: 'Message sent to #general'
                        error:
                          type: object
                          nullable: true
                          description: >-
                            Error payload when `status` is not `success`.
                            Includes `code`, `message`, `type`, and optional
                            `param` and `details`.
                          example: null
              examples:
                success:
                  summary: Single successful call
                  value:
                    results:
                      - ref: send-1
                        server_id: gumloop_slack
                        tool_name: slack_send_message
                        status: success
                        content:
                          - type: text
                            text: 'Message sent to #general'
                        error: null
                execution_error:
                  summary: Tool execution failed
                  value:
                    results:
                      - ref: issue-1
                        server_id: gumloop_linear
                        tool_name: linear_create_issue
                        status: unauthenticated
                        content: null
                        error:
                          code: auth_required
                          message: Connect Linear before using this tool.
                          type: permission_error
                          param: tool_name
                          details:
                            server_id: gumloop_linear
                            tool_name: linear_create_issue
                            gumloop_auth_url: https://www.gumloop.com/oauth/connect/linear
        '207':
          description: >-
            Partial success — at least one call succeeded and at least one
            failed. Inspect each result's `status` and `error`.
          content:
            application/json:
              schema:
                type: object
                properties:
                  results:
                    type: array
                    items:
                      type: object
              examples:
                mixed:
                  summary: One success, one needs authentication
                  value:
                    results:
                      - ref: send-1
                        server_id: gumloop_slack
                        tool_name: slack_send_message
                        status: success
                        content:
                          - type: text
                            text: 'Message sent to #general'
                        error: null
                      - ref: issue-1
                        server_id: gumloop_linear
                        tool_name: linear_create_issue
                        status: unauthenticated
                        content: null
                        error:
                          code: auth_required
                          message: Connect Linear before using this tool.
                          type: permission_error
                          param: tool_name
                          details:
                            server_id: gumloop_linear
                            tool_name: linear_create_issue
                            gumloop_auth_url: https://www.gumloop.com/oauth/connect/linear
        '400':
          description: >-
            Invalid Gumloop request body, for example fewer than 1 or more than
            5 calls.
        '401':
          description: Unauthorized — missing or invalid Gumloop API credentials.
        '403':
          description: >-
            Forbidden — the caller lacks permission to use this Gumloop API
            endpoint or requested team scope.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: |
            curl 'https://api.gumloop.com/api/v1/mcp/tools/call' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "team_id": "team_4f8c92ab",
                "calls": [
                  {
                    "ref": "send-1",
                    "server_id": "gumloop_slack",
                    "tool_name": "slack_send_message",
                    "arguments": {"channel": "#general", "text": "Hello from Gumloop"}
                  }
                ]
              }'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.mcp.execute(
                server_id="gumloop_slack",
                tool_name="slack_send_message",
                arguments={"channel": "#general", "text": "Hello from Gumloop"},
                ref="send-1",
                team_id="team_4f8c92ab",
            )
            for result in response.results:
                print(result.ref, result.status)
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