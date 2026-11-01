> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Website Crawler

This document explains the Website Crawler node, which gathers all links from a website by traversing its pages.

## Node Inputs

### Required Fields

* **URL**: Starting web address

  Example: "[https://www.gumloop.com/](https://www.gumloop.com/)"
* **Depth**: How many layers to crawl (1-3)
  * 1: Only starting page links
  * 2: Starting page + linked pages
  * 3: Three layers deep (maximum)

### Optional Fields

* **Limit to Same Domain**: Only collect URLs from same website

### Show As Input Options

You can expose these fields as inputs:

* URL
* Depth

## Node Output

* **URL List**: All discovered web addresses

## Node Functionality

The Website Crawler node:

* Visits web pages systematically
* Collects all found links
* Follows links to specified depth
* Can stay within one domain
* Returns complete URL list

## Common Use Cases

1. **Website Mapping**:

```text theme={"dark"}
URL: Your website
Depth: 2
Use: Find all connected pages
```

2. **Content Discovery**:

```text theme={"dark"}
URL: Blog homepage
Depth: 1
Use: Find all article links
```

3. **SEO Analysis**:

```text theme={"dark"}
URL: Competitor site
Depth: 3
Use: Analyze site structure
```

## Important Considerations

1. Higher depths take exponentially longer
2. Consider domain limits for focus
3. URLs must include `https://`

In summary, the Website Crawler node helps map website structures by systematically collecting links, with controls for depth and domain scope.
