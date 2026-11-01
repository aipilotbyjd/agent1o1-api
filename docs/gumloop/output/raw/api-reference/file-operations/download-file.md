> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Download file



## OpenAPI

````yaml post /download_file
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /download_file:
    post:
      tags:
        - File Handling
      summary: Download file
      operationId: downloadFile
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                file_name:
                  type: string
                  description: The name of the file to download.
                run_id:
                  type: string
                  description: The ID of the flow run associated with the file.
                saved_item_id:
                  type: string
                  description: The saved item ID associated with the file.
                user_id:
                  type: string
                  description: Optional. The user ID associated with the flow run.
                project_id:
                  type: string
                  description: Optional. The project ID associated with the flow run.
      responses:
        '200':
          description: File downloaded successfully
        '400':
          description: Bad request (missing file_name or other required data)
        '403':
          description: Unauthorized (API key or user verification failed)
        '500':
          description: Internal server error (file download failed)
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