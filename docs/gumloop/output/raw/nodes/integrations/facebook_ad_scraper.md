> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Facebook Ad Library Scraper

Scrape and analyze active ads from Facebook's Ad Library. This node enables systematic collection of ad data either by targeting specific business pages or searching for keywords.

## Credits and Usage

* Costs 2 credits per specified numbers of ad to scrape
* Each request fetches your specified number of ads (minimum 10)
* Example: Scraping 10 ads = 20 credits

## Core Parameters

### 1. Search Method (Required, Choose One)

#### Option A: Profile URL

* Format: Only accepts Facebook page URLs (not Instagram, Facebook Ad Library, or other links)
* Examples:
  * ✓ `https://www.facebook.com/google`
  * ✓ `https://www.facebook.com/nike`
  * ✗ `https://www.instagram.com/nike`
  * ✗ `https://www.facebook.com/ads/library/...`
* Requirements:
  * Must be a valid Facebook page URL
  * Page must be running ads

#### Option B: Search Keywords

* Format: Text string
* Examples:
  * "Christmas mugs"
  * "Online courses"
  * "Fitness supplements"
* Best Practices:
  * Use specific terms for targeted results
  * Consider common variations of keywords

### 2. Optional Filters

#### Ad Country

* Format: Two-letter country code
* Default: Leave blank for all countries
* Examples:
  * US (United States)
  * UK (United Kingdom)
  * CA (Canada)
  * AU (Australia)
* Important Notes:
  * Some ads may only be visible in specific countries
  * To see region-specific ads, select the appropriate country
  * Leave it blank to see ads available across all regions

#### Media Type

* Options:
  * All (Default)
  * Images
  * Memes
  * Videos
  * No Media
* Usage: The type of media to filter ads by. Images are ads with images and little to no text. Memes are images with text.

#### Platforms

* Multiple selection allowed:
  * Facebook
  * Instagram
  * Audience Network
  * Messenger
* Default: All platforms selected
  The platforms for which the ad was approved to run ads on by the advertiser. Every scraped ad will be on at least one of these platforms.

#### Max Number of Ads

* Minimum: 10
* Default: 10
* Purpose: Controls the number of ads to retrieve
* Credit Impact: 2 credits per number of ads specified

### More Options

#### Prefer Video Thumbnails

* Toggle to fetch video thumbnails instead of full video URLs for video ads
* Options:
  * Yes: Returns thumbnail images for video ads
  * No (Default): Returns full video URLs

#### Ad Active Status

* Filter ads based on their current serving status
* Options:
  * Active: Only fetch ads currently being served to audiences
  * Inactive: Only fetch ads that are no longer running
  * All (Default): Fetch both active and inactive ads
* Note: Default option is `Active`

## Output Data Fields

### 1. Library IDs

* Unique identifier for each Facebook ad
* Format: Numeric string
* Example: "12345678901234567"

### 2. Ad Media URLs

* Direct links to ad creative assets
* Types:
  * Image URLs
  * Video URLs
* Format: Full URLs to media resources

> To fetch video thumbnails instead of the video itself for ads with videos, check `Prefer Video Thumbnails` under 'More Options'

### 3. Platforms

* List of platforms where each ad appears
* Output format: Each platform separated by a comma

### 4. Start Date

* When the ad began running
* Format: Date string
* Example: `2024-12-10T08:00:00`

### 5. Body Text

* Complete ad copy content
* Includes:
  * Main text
  * Headlines
  * Descriptions

## Example Use Cases

### Competitor Ad Monitoring System

**Workflow:**

* Facebook Ad Scraper → Airtable/Database → Slack Alert
* **Input:**
  * Profile URL: Competitor's Facebook page
  * Max Ads: 10
  * Media Type: All
* **Automation Value:**
  * Automatically logs competitor's new ads to your database
  * Sends Slack alerts when new campaigns launch
  * Maintains historical record of competitor's ad strategies

### AI Ad Copy Generator

**Workflow:**

* Facebook Ad Scraper → AI Analysis → AI Copy Generator → Google Doc/Notion
* **Input:**
  * Keywords: Your industry terms
  * Max Ads: 10
  * Media Type: All
* **Automation Value:**
  * Analyzes successful ads in your niche
  * Uses AI to generate fresh ad copy ideas
  * Creates a Google Doc or a Notion page with new ad suggestions
  * Regular inspiration for your ad campaigns

### Lead Generation Pipeline

**Workflow:**

* Facebook Ad Scraper → Filter by Keywords → LinkedIn Enrichment → Google Sheets/Airtable
* **Input:**
  * Keywords: "hiring" or "now open"
  * Max Ads: 10
  * Country: Target market
* **Automation Value:**
  * Identifies businesses actively expanding
  * Enriches data with LinkedIn company info
  * Automatically adds prospects to your database
  * Creates targeted outreach opportunities

## Limitations

1. Data Access
   * Only retrieves currently active ads
   * Some ad details may be restricted by Facebook
   * Results depend on Facebook's Ad Library availability

2. Geographic Restrictions
   * Ad visibility varies by country
   * Some ads may only be visible in specific regions
