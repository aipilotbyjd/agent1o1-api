> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Manage workspace users

> This endpoint allows organization administrators to add or remove users from a workspace.



## OpenAPI

````yaml post /manage_workspace_users
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /manage_workspace_users:
    post:
      tags:
        - Organization
      summary: Manage workspace users
      description: >-
        This endpoint allows organization administrators to add or remove users
        from a workspace.
      operationId: manageProjectUsers
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                organization_id:
                  type: string
                  description: The ID of the organization that the workspace belongs to.
                user_id:
                  type: string
                  description: >-
                    Your user id -- you must be an organization admin to manage
                    workspace users.
                workspace_id:
                  type: string
                  description: The ID of the workspace to manage users for.
                action:
                  type: string
                  enum:
                    - add
                    - remove
                  description: The action to perform - either 'add' or 'remove' a user.
                user_email:
                  type: string
                  description: The email address of the target user to add or remove.
                is_admin:
                  type: boolean
                  description: >-
                    When adding a user, specify whether they should have admin
                    privileges (default is false).
              required:
                - organization_id
                - user_id
                - workspace_id
                - action
                - user_email
      responses:
        '200':
          description: User successfully added to or removed from workspace
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
            Forbidden (user is not an organization admin or workspace doesn't
            belong to organization)
        '404':
          description: Organization, workspace, or user not found
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