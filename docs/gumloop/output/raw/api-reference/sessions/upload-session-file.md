> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Upload session file

> Upload a file into a session's input namespace so it can be attached to a message. The response returns the stored path — pass it as `file_name` in the `attachments` array when sending a message on the same session.

Files are base64 encoded in the request body and limited to 200MB (decoded). Uploaded files are scoped to the session they were uploaded to and cannot be attached to messages on other sessions.




## OpenAPI

````yaml post /sessions/{session_id}/files
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /sessions/{session_id}/files:
    post:
      tags:
        - Sessions
      summary: Upload session file
      description: >
        Upload a file into a session's input namespace so it can be attached to
        a message. The response returns the stored path — pass it as `file_name`
        in the `attachments` array when sending a message on the same session.


        Files are base64 encoded in the request body and limited to 200MB
        (decoded). Uploaded files are scoped to the session they were uploaded
        to and cannot be attached to messages on other sessions.
      operationId: uploadSessionFile
      parameters:
        - in: path
          name: session_id
          required: true
          schema:
            type: string
          description: ID of the session to upload the file to.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - file_name
                - file_content
              properties:
                file_name:
                  type: string
                  description: >-
                    Name of the file. Directory components are stripped; the
                    base name is sanitized before storage.
                  example: report.pdf
                file_content:
                  type: string
                  format: byte
                  description: Base64-encoded file contents. Maximum decoded size is 200MB.
                media_type:
                  type: string
                  description: MIME type of the file. Echoed back in the response.
                  example: application/pdf
      responses:
        '201':
          description: >-
            File stored. Use `file_name` from the response as an attachment on
            this session's messages.
          content:
            application/json:
              schema:
                type: object
                properties:
                  file_name:
                    type: string
                    description: >-
                      Stored path of the file inside the session's input
                      namespace.
                    example: custom_agent_interactions/sess_xYz789AbCd/input/report.pdf
                  media_type:
                    type: string
                    nullable: true
                    example: application/pdf
                  size:
                    type: integer
                    description: Decoded file size in bytes.
                    example: 482133
              example:
                file_name: custom_agent_interactions/sess_xYz789AbCd/input/report.pdf
                media_type: application/pdf
                size: 482133
        '400':
          description: >-
            Bad request — empty or invalid `file_name` (`invalid_file_name`), or
            `file_content` is not valid base64 (`invalid_file_content`).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access on the session.
        '404':
          description: Session not found.
        '413':
          description: >-
            File too large — the file exceeds the 200MB limit
            (`file_too_large`).
        '500':
          description: >-
            Internal server error — the file could not be stored
            (`file_upload_failed`).
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X POST
            'https://api.gumloop.com/api/v1/sessions/sess_xYz789AbCd/files' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{
                "file_name": "report.pdf",
                "file_content": "'"$(base64 -w 0 report.pdf)"'",
                "media_type": "application/pdf"
              }'
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