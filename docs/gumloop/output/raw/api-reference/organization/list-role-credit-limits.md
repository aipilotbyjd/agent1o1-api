> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List custom role credit limits

> This endpoint lists every active custom role in an organization together with its monthly credit limit, so external systems can manage credit limits programmatically. A `monthly_credit_limit` of `null` means the role sets no limit of its own. The limit applies to each member of the role individually; when a user belongs to multiple roles, the highest limit across their roles wins.




## OpenAPI

````yaml get /organizations/{organization_id}/roles/credit-limits
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /organizations/{organization_id}/roles/credit-limits:
    get:
      tags:
        - Organization
      summary: List custom role credit limits
      description: >
        This endpoint lists every active custom role in an organization together
        with its monthly credit limit, so external systems can manage credit
        limits programmatically. A `monthly_credit_limit` of `null` means the
        role sets no limit of its own. The limit applies to each member of the
        role individually; when a user belongs to multiple roles, the highest
        limit across their roles wins.
      operationId: listRoleCreditLimits
      parameters:
        - in: path
          name: organization_id
          required: true
          schema:
            type: string
          description: The ID of the organization.
        - in: query
          name: user_id
          required: true
          schema:
            type: string
          description: >-
            Your user id -- you must be an organization admin to manage custom
            role credit limits.
        - in: query
          name: page_size
          required: false
          schema:
            type: integer
            default: 20
            maximum: 100
          description: Number of roles per page.
        - in: query
          name: cursor
          required: false
          schema:
            type: string
          description: >-
            Opaque cursor from a previous response's `next_cursor`; omit for the
            first page.
      responses:
        '200':
          description: Custom roles with their credit limits
          content:
            application/json:
              schema:
                type: object
                properties:
                  roles:
                    type: array
                    items:
                      $ref: '#/components/schemas/RoleCreditLimit'
                  next_cursor:
                    type: string
                    nullable: true
                    description: >-
                      Cursor for the next page, or null when there are no more
                      roles.
              example:
                roles:
                  - role_id: aBcDeFgHiJkLmNoPqRsTuV
                    role_name: Engineering
                    is_default: false
                    member_count: 42
                    monthly_credit_limit: 5000
                  - role_id: xYzAbCdEfGhIjKlMnOpQrS
                    role_name: Everyone
                    is_default: true
                    member_count: 310
                    monthly_credit_limit: null
                next_cursor: null
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: >-
            Forbidden (user is not an organization admin or the organization is
            not on the Enterprise plan)
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