> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve audit logs

> This endpoint retrieves audit logs for all users in an organization for a specified time period.



## OpenAPI

````yaml get /get_audit_logs
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /get_audit_logs:
    get:
      tags:
        - Organization
      summary: Retrieve audit logs
      description: >-
        This endpoint retrieves audit logs for all users in an organization for
        a specified time period.
      operationId: getOrganizationAuditLogs
      parameters:
        - in: query
          name: organization_id
          required: true
          schema:
            type: string
          description: The ID of the organization to retrieve audit logs for.
        - in: query
          name: user_id
          required: true
          schema:
            type: string
          description: >-
            Your user id -- you must be an organization admin to retrieve
            organization logs.
        - in: query
          name: start_time
          required: true
          schema:
            type: string
            format: date-time
          description: Start timestamp for log filtering (ISO format).
        - in: query
          name: end_time
          required: true
          schema:
            type: string
            format: date-time
          description: End timestamp for log filtering (ISO format).
        - in: query
          name: page
          required: false
          schema:
            type: integer
            default: 1
          description: Page number for pagination.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            default: 50
          description: Number of records per page.
      responses:
        '200':
          description: Successful retrieval of organization audit logs
          content:
            application/json:
              schema:
                type: object
                properties:
                  audit_logs:
                    type: array
                    items:
                      type: object
                      properties:
                        event_id:
                          type: string
                          description: Unique identifier for the audit log event.
                        timestamp:
                          type: string
                          format: date-time
                          description: When the event occurred.
                        event_type:
                          type: string
                          description: Type of event that was logged.
                        user_id:
                          type: string
                          description: ID of the user who performed the action.
                        details:
                          type: string
                          description: Additional details about the event.
                        source_ip:
                          type: string
                          description: IP address from which the action was performed.
                        user_agent:
                          type: string
                          description: User agent information from the request.
                  total_count:
                    type: integer
                    description: Total number of matching audit logs.
                  page:
                    type: integer
                    description: Current page number.
                  page_size:
                    type: integer
                    description: Number of records per page.
                  total_pages:
                    type: integer
                    description: Total number of pages available.
        '400':
          description: Bad request (missing required parameters)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (user is not an organization admin)
        '404':
          description: Organization not found
        '500':
          description: Internal server error
      security:
        - bearerAuth: []
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