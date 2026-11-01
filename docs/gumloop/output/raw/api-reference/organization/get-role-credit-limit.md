> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Get custom role credit limit

> This endpoint returns the monthly credit limit of one custom role. A `monthly_credit_limit` of `null` means the role sets no limit of its own.




## OpenAPI

````yaml get /organizations/{organization_id}/roles/{role_id}/credit-limit
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /organizations/{organization_id}/roles/{role_id}/credit-limit:
    get:
      tags:
        - Organization
      summary: Get custom role credit limit
      description: >
        This endpoint returns the monthly credit limit of one custom role. A
        `monthly_credit_limit` of `null` means the role sets no limit of its
        own.
      operationId: getRoleCreditLimit
      parameters:
        - in: path
          name: organization_id
          required: true
          schema:
            type: string
          description: The ID of the organization the custom role belongs to.
        - in: path
          name: role_id
          required: true
          schema:
            type: string
          description: >-
            The ID of the custom role (the same ID used as `group_id` by the
            Manage custom role users endpoint).
        - in: query
          name: user_id
          required: true
          schema:
            type: string
          description: >-
            Your user id -- you must be an organization admin to manage custom
            role credit limits.
      responses:
        '200':
          description: The custom role and its credit limit
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RoleCreditLimit'
              example:
                role_id: aBcDeFgHiJkLmNoPqRsTuV
                role_name: Engineering
                is_default: false
                member_count: 42
                monthly_credit_limit: 5000
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: >-
            Forbidden (user is not an organization admin or the organization is
            not on the Enterprise plan)
        '404':
          description: Custom role not found in this organization
        '500':
          description: Internal server error
      security:
        - bearerAuth: []
components:
  schemas:
    RoleCreditLimit:
      type: object
      properties:
        role_id:
          type: string
          description: The ID of the custom role.
        role_name:
          type: string
          description: The name of the custom role.
        is_default:
          type: boolean
          description: >-
            Whether this is a default role that new organization members are
            assigned to.
        member_count:
          type: integer
          description: Number of users currently assigned to this role.
        monthly_credit_limit:
          type: integer
          nullable: true
          description: >-
            The monthly credit limit applied to each member of this role, or
            null when the role sets no limit.
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````