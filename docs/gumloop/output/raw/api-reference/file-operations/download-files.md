> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Download multiple files



## OpenAPI

````yaml post /download_files
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /download_files:
    post:
      tags:
        - File Handling
      summary: Download multiple files
      operationId: downloadFiles
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                file_names:
                  type: array
                  items:
                    type: string
                  description: An array of file names to download.
                run_id:
                  type: string
                  description: The ID of the flow run associated with the files.
                user_id:
                  type: string
                  description: >-
                    The user ID associated with the files. Required if
                    project_id is not provided.
                project_id:
                  type: string
                  description: >-
                    The project ID associated with the files. Required if
                    user_id is not provided.
                saved_item_id:
                  type: string
                  description: Optional. The saved item ID associated with the files.
      responses:
        '200':
          description: Files downloaded successfully as a zip
          content:
            application/zip:
              schema:
                type: string
                format: binary
        '400':
          description: Bad request (missing file_names or other required data)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (API key does not match)
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