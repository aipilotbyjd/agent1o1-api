> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Generate Image

The Generate Image node allows you to create AI-generated images based on text descriptions.

## Available Models

| Model                                | Provider | Best For                               |
| ------------------------------------ | -------- | -------------------------------------- |
| **GPT-Image**                        | OpenAI   | Highest quality, photorealistic images |
| **Gemini 3.1 Flash (Nano Banana 2)** | Google   | Latest Google image generation model   |
| **DALL-E 3**                         | OpenAI   | Creative, artistic images              |
| **DALL-E 2**                         | OpenAI   | Fast generation, simpler tasks         |
| **Gemini 3 Pro Nano Banana**         | Google   | Integrated text-to-image               |
| **Gemini 2.5 Flash Nano Banana**     | Google   | Quick image generation                 |

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Text Prompt"] --> B["Generate Image"]
      B --> C["Image URLs"]
      C --> D["Various Uses"]
      D --> E["Slack\nMessage"]
      D --> F["Google Drive\nFile"]
      D --> G["Upload to\nDatabase"]
  ```
</div>

## Node Inputs

### Required Fields

* **Prompt**: A detailed description of the image you want to create

  Example: "A professional product photo of a sleek smartphone on a marble desk with soft lighting and minimal shadows"

### Optional Fields

* **Number of Images**: How many image variations to generate (1-10)
  * Default: 1
  * Note: Each additional image costs 30 credits

### Configuration Options

* **Quality**: Controls the detail and resolution of generated images
  * **Auto**: System determines optimal quality (default)
  * **High**: Maximum detail and clarity
  * **Medium**: Balanced quality and time to generate
  * **Low**: More efficient option for drafts or less critical uses

* **Size**: Dimensions of the generated image
  * **Auto**: System determines optimal size (default)
  * **1024x1024**: Standard square format
  * **1536x1024**: Landscape orientation
  * **1024x1536**: Portrait orientation

* **Background**: Controls the background type
  * **Auto**: System determines appropriate background (default)
  * **Transparent**: Creates image with transparent background (works with PNG format)
  * **Opaque**: Ensures solid background

* **Output Format**: Image file format
  * **PNG**: Supports transparency, best for graphics
  * **JPEG**: More compressed, suitable for photographs
  * **WebP**: Modern format with good compression and quality

## Node Output

* **Image URLs**: List of URLs pointing to the generated images
  * Format: A list of text strings, each containing a full URL
  * Note: These URLs can be used directly with other nodes like Slack Message Sender, Google Drive File Writer, etc.

## Credit Cost

* **30 credits per image generated**
* This cost is fixed regardless of quality settings, size, or format

## Common Use Cases

### Marketing and Advertising

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Sheets Reader\n(Campaign Calendar)"] --> B["Generate Image\n(Social Media Visuals)"]
      B --> C["Slack Message Sender\n(Review Approval)"]
      C --> D["Twitter Poster\n(Publish Content)"]
  ```
</div>

```text theme={"dark"}
Prompt: "A lifestyle photo of someone using our fitness app on a smartphone while at the gym, with subtle brand colors in the background"
Quality: High
Format: JPEG
Use: Social media marketing campaign
```

### Product Visualization

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Airtable Reader\n(Product Database)"] --> B["Ask AI\n(Generate Product Mockup Prompt)"]
      B --> C["Generate Image\n(Product Visualization)"]
      C --> D["Google Drive File Writer\n(Save Product Assets)"]
  ```
</div>

```text theme={"dark"}
Prompt: "360-degree view of a minimalist desk lamp with brushed aluminum finish on a white background"
Quality: High
Background: Transparent
Format: PNG
Use: E-commerce product listings
```

### Content Creation

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Ask AI\n(Generate Blog Content)"] --> B["Extract Data\n(Key Points)"]
      B --> C["Generate Image\n(Visual Elements)"]
      C --> D["Ghost Blog Poster\n(Publish Blog)"]
  ```
</div>

```text theme={"dark"}
Prompt: "Infographic showing 5 steps of customer onboarding process with blue and green color scheme"
Size: 1024x1536
Quality: Medium
Use: Blog post or newsletter
```

### UI/UX Mockups

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Notion Database Reader\n(Design Requirements)"] --> B["Generate Image\n(UI Mockups)"]
      B --> C["Notion Database Updater\n(Design Reviews)"]
  ```
</div>

```text theme={"dark"}
Prompt: "Mobile app dashboard for financial tracking with dark mode interface, showing graphs and statistics"
Size: 1024x1536
Quality: Medium
Format: PNG
Use: Product demonstration or investor presentation
```

## Effective Prompt Strategies

### Structure

For best results, include these elements in your prompts:

1. **Subject**: What is the main focus?
2. **Style**: Photo, illustration, 3D render, etc.
3. **Setting**: Where is the subject located?
4. **Lighting**: How is the scene lit?
5. **Perspective**: What angle is the subject viewed from?
6. **Colors**: What is the color scheme?

### Examples of Strong Prompts

**Basic prompt:**
"A coffee cup on a table"

**Improved prompt:**
"A ceramic coffee cup with steam rising, placed on a rustic wooden table by a window with morning sunlight streaming in, photographed with shallow depth of field, warm color tones"

## Integration with Other Nodes

The Generate Image node pairs effectively with:

* **Slack Message Sender**: Share generated images directly in channels or DMs
* **Google Drive File Writer**: Save images to your Drive for storage or sharing
* **Gmail Sender**: Include generated images as email attachments
* **Content Management Nodes**: Incorporate images into CMS posts via API

## Important Considerations

* **Restricted Content**: The model won't generate inappropriate or harmful imagery. Refer to OpenAI's docs for content guidelines.

## Troubleshooting

If your images aren't generating as expected:

* **Prompt Clarity**: Be more specific about what you want to see
* **Style Guidance**: Explicitly mention the artistic style or medium
* **Composition Details**: Specify camera angle, lighting, and scene details
* **Technical Terms**: Use photography or design terminology for more precise control
* **Iteration**: Try variations of your prompt to find what works best using `Loop Mode`
