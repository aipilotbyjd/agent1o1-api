> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Get data export status

> This endpoint retrieves the status of a data export job and optionally downloads the export file (as CSV) if the export has completed successfully.

Use the `data_export_id` returned by the [Export data](/api-reference/organization/export-data) endpoint to check progress.




## OpenAPI

````yaml get /export_status
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /export_status:
    get:
      tags:
        - Organization
      summary: Get data export status
      description: >
        This endpoint retrieves the status of a data export job and optionally
        downloads the export file (as CSV) if the export has completed
        successfully.


        Use the `data_export_id` returned by the [Export
        data](/api-reference/organization/export-data) endpoint to check
        progress.
      operationId: exportStatus
      parameters:
        - in: query
          name: user_id
          required: true
          schema:
            type: string
          description: The ID of the user requesting the export status.
        - in: query
          name: data_export_id
          required: true
          schema:
            type: string
          description: >-
            The unique identifier of the data export job to check (returned by
            the Export data endpoint).
        - in: query
          name: download
          required: false
          schema:
            type: boolean
            default: false
          description: >
            Set to `true` to download the export file directly when the export
            is completed. When `true` and the export state is `COMPLETED`, the
            response will be a CSV file download instead of JSON.
      responses:
        '200':
          description: >
            Export status retrieved successfully. If `download=true` and the
            export state is `COMPLETED`, the response body will be the CSV file.
          content:
            application/json:
              schema:
                type: object
                properties:
                  data_export_id:
                    type: string
                    description: The unique identifier of the data export job.
                  state:
                    type: string
                    enum:
                      - REQUESTED
                      - IN_PROGRESS
                      - COMPLETED
                      - FAILED
                    description: >
                      Current state of the export job:

                      - `REQUESTED` — The export has been created and is queued
                      for processing.

                      - `IN_PROGRESS` — The export is currently being processed.

                      - `COMPLETED` — The export finished successfully and is
                      available for download.

                      - `FAILED` — The export encountered an error.
                  created_ts:
                    type: string
                    format: date-time
                    description: Timestamp when the export was created (ISO 8601).
                  finished_ts:
                    type: string
                    format: date-time
                    description: >-
                      Timestamp when the export finished (ISO 8601). Only
                      present when the state is `COMPLETED` or `FAILED`.
            text/csv:
              schema:
                type: string
                format: binary
                description: >-
                  CSV file content returned when `download=true` and the export
                  state is `COMPLETED`.
        '400':
          description: Bad request — missing `data_export_id` parameter.
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — insufficient permissions, the export does not belong to
            the requesting user, or the organization is not on the enterprise
            tier.
        '404':
          description: Data export not found, or the export file is no longer available.
        '500':
          description: Internal server error.
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