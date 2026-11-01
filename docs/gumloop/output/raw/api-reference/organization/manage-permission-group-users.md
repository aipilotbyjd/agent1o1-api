> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Manage custom role users

> This endpoint allows organization administrators to add or remove users from a custom role (formerly "permission group"). Adding a user to a role does not remove them from any other role they belong to.



## OpenAPI

````yaml post /manage_permission_group_users
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /manage_permission_group_users:
    post:
      tags:
        - Organization
      summary: Manage custom role users
      description: >-
        This endpoint allows organization administrators to add or remove users
        from a custom role (formerly "permission group"). Adding a user to a
        role does not remove them from any other role they belong to.
      operationId: managePermissionGroupUsers
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                organization_id:
                  type: string
                  description: The ID of the organization that the custom role belongs to.
                user_id:
                  type: string
                  description: >-
                    Your user id -- you must be an organization admin to manage
                    custom role users.
                group_id:
                  type: string
                  description: The ID of the custom role to manage users for.
                action:
                  type: string
                  enum:
                    - add
                    - remove
                  description: The action to perform - either 'add' or 'remove' a user.
                user_email:
                  type: string
                  description: The email address of the target user to add or remove.
              required:
                - organization_id
                - user_id
                - group_id
                - action
                - user_email
      responses:
        '200':
          description: User successfully added to or removed from the custom role
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  message:
                    type: string
                    description: A success message describing the action performed.
        '400':
          description: Bad request (missing required parameters or invalid action)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: >-
            Forbidden (user is not an organization admin or custom role doesn't
            belong to organization)
        '404':
          description: Organization, custom role, or user not found
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