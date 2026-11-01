> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Set custom role credit limit

> This endpoint sets or clears the monthly credit limit of a custom role. The limit applies to each member of the role individually and takes effect immediately: member allowances are recalculated while preserving credits already used in the current billing cycle. Send `"monthly_credit_limit": null` to clear the role-level limit so members revert to the organization default. When a user belongs to multiple roles, the highest limit across their roles wins. Requests that do not change the stored value are no-ops. Changes are recorded in the organization audit trail.




## OpenAPI

````yaml put /organizations/{organization_id}/roles/{role_id}/credit-limit
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /organizations/{organization_id}/roles/{role_id}/credit-limit:
    put:
      tags:
        - Organization
      summary: Set custom role credit limit
      description: >
        This endpoint sets or clears the monthly credit limit of a custom role.
        The limit applies to each member of the role individually and takes
        effect immediately: member allowances are recalculated while preserving
        credits already used in the current billing cycle. Send
        `"monthly_credit_limit": null` to clear the role-level limit so members
        revert to the organization default. When a user belongs to multiple
        roles, the highest limit across their roles wins. Requests that do not
        change the stored value are no-ops. Changes are recorded in the
        organization audit trail.
      operationId: setRoleCreditLimit
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
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                monthly_credit_limit:
                  type: integer
                  nullable: true
                  minimum: 0
                  maximum: 1000000000
                  description: >-
                    The monthly credit limit applied to each member of this
                    role, or null to clear the role-level limit.
                user_id:
                  type: string
                  description: >-
                    Your user id -- you must be an organization admin to manage
                    custom role credit limits.
              required:
                - monthly_credit_limit
                - user_id
            example:
              monthly_credit_limit: 10000
              user_id: xxxxxxxxxxxxxx
      responses:
        '200':
          description: The custom role with its updated credit limit
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RoleCreditLimit'
              example:
                role_id: aBcDeFgHiJkLmNoPqRsTuV
                role_name: Engineering
                is_default: false
                member_count: 42
                monthly_credit_limit: 10000
        '400':
          description: Bad request (missing or invalid monthly_credit_limit)
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