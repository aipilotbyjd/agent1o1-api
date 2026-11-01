> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Shopify

> Manage your Shopify store, blog posts, and e-commerce operations with AI-powered automation.

Shopify is a leading e-commerce platform for online stores and retail point-of-sale systems. The Shopify MCP server lets you manage products, orders, customers, inventory, collections, discounts, fulfillment, blog posts, metafields, and more using natural language.

## What Can It Do?

* **Manage products** — search, create, update, delete products and variants
* **Track inventory** — check levels, adjust quantities, and manage locations
* **Handle orders** — search, view, cancel, close, tag, and edit orders
* **Process refunds and returns** — create refunds and returns on orders
* **Manage customers** — search, create, and update customer records
* **Fulfill orders** — create fulfillments, update tracking, and cancel shipments
* **Organize collections** — create, update, delete collections and manage product groupings
* **Run discounts** — create discount codes, automatic discounts, free shipping, and BXGY deals
* **Manage draft orders** — create, complete, and delete draft orders
* **Track abandoned checkouts** — search and view incomplete carts
* **Update store content** — create and manage online store pages
* **Manage blogs and articles** — create blogs, publish posts, and moderate comments
* **Work with metafields** — get, set, and delete metafields on any resource

## Where to Use It

### In Agents (Recommended)

Add Shopify as a tool to any agent. The agent can then manage your store conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Shopify tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search for out-of-stock products")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Shop

| Tool                 | Description                                                                |
| -------------------- | -------------------------------------------------------------------------- |
| **Get Shop Details** | Get store details including name, domain, currency, plan, and contact info |

### Products

| Tool                         | Description                                                                             |
| ---------------------------- | --------------------------------------------------------------------------------------- |
| **Search Products**          | Search products with query filters, sort, and pagination                                |
| **Get Product**              | Get a single product by ID with full details including variants, images, and metafields |
| **Create Product**           | Create a new product                                                                    |
| **Update Product**           | Update an existing product's details                                                    |
| **Delete Product**           | Delete a product and all associated variants and media                                  |
| **Create Product Variant**   | Add a new variant to an existing product                                                |
| **Update Product Variant**   | Update a variant's price, SKU, weight, or other properties                              |
| **Reorder Product Variants** | Reorder variants on a product                                                           |
| **Delete Product Variants**  | Delete one or more variants from a product in bulk                                      |
| **Add Product Image**        | Add an image to a product from a URL                                                    |
| **Delete Product Media**     | Delete media from a product                                                             |
| **Reorder Product Media**    | Reorder media on a product                                                              |
| **Update Product Media**     | Update media on a product                                                               |

### Inventory

| Tool                           | Description                                                           |
| ------------------------------ | --------------------------------------------------------------------- |
| **Get Locations**              | Get all inventory locations (warehouses, stores, fulfillment centers) |
| **Get Inventory Level**        | Get inventory quantities for an item across all locations             |
| **Adjust Inventory**           | Adjust inventory quantity for an item at a specific location          |
| **Update Inventory Tracking**  | Enable or disable inventory tracking for an inventory item            |
| **Get Variant Inventory Item** | Get the inventory item ID and levels for a product variant            |

### Orders

| Tool                                  | Description                                                                     |
| ------------------------------------- | ------------------------------------------------------------------------------- |
| **Search Orders**                     | Search orders by status, date, customer, or fulfillment status                  |
| **Get Order**                         | Get a single order by ID with full details                                      |
| **Cancel Order**                      | Cancel an order with a reason, optionally restocking and notifying the customer |
| **Close Order**                       | Close (archive) a fully-processed order                                         |
| **Update Order Note**                 | Update the internal staff note and other editable fields on an order            |
| **Add Order Tags**                    | Add tags to an order for categorization and filtering                           |
| **Create Refund**                     | Refund line items, shipping, and duties on an order with optional restocking    |
| **Create Return**                     | Create a return on an order for fulfilled items                                 |
| **Begin Order Edit**                  | Start an order editing session                                                  |
| **Order Edit Add Variant**            | Add a product variant as a new line item to an order being edited               |
| **Order Edit Set Line Item Quantity** | Set the quantity of a line item on an order being edited                        |
| **Commit Order Edit**                 | Apply staged order edit changes to the original order                           |

### Abandoned Checkouts

| Tool                           | Description                                                                                      |
| ------------------------------ | ------------------------------------------------------------------------------------------------ |
| **Search Abandoned Checkouts** | Search abandoned checkouts (incomplete carts) with filters for status, recovery state, and dates |
| **Get Abandoned Checkout**     | Get a single abandoned checkout with full details                                                |

### Customers

| Tool                 | Description                                                         |
| -------------------- | ------------------------------------------------------------------- |
| **Search Customers** | Search customers by name, email, phone, tags, or other criteria     |
| **Get Customer**     | Get a single customer by ID with full details                       |
| **Create Customer**  | Create a new customer with contact information, addresses, and tags |
| **Update Customer**  | Update an existing customer's details                               |

### Fulfillment

| Tool                            | Description                                            |
| ------------------------------- | ------------------------------------------------------ |
| **Get Fulfillment Orders**      | Get fulfillment orders for a specific order            |
| **Create Fulfillment**          | Create a fulfillment with tracking information         |
| **Update Fulfillment Tracking** | Update tracking information on an existing fulfillment |
| **Cancel Fulfillment**          | Cancel a fulfillment, restocking the items             |

### Collections

| Tool                                | Description                                          |
| ----------------------------------- | ---------------------------------------------------- |
| **Search Collections**              | Search collections by title, type, or other criteria |
| **Get Collection**                  | Get a single collection by ID including its products |
| **Create Collection**               | Create a custom or smart collection                  |
| **Update Collection**               | Update a collection's details                        |
| **Delete Collection**               | Delete a collection                                  |
| **Add Products to Collection**      | Add products to a custom collection                  |
| **Remove Products from Collection** | Remove products from a custom collection             |

### Discounts

| Tool                              | Description                                                          |
| --------------------------------- | -------------------------------------------------------------------- |
| **Search Discount Codes**         | Search discount codes                                                |
| **Create Discount Code**          | Create a percentage or fixed-amount discount code                    |
| **Create Automatic Discount**     | Create an automatic discount that applies at checkout without a code |
| **Delete Discount**               | Delete a discount code or automatic discount                         |
| **Update Discount**               | Update an existing discount                                          |
| **Create Free Shipping Discount** | Create a free shipping discount                                      |
| **Create BXGY Discount**          | Create a buy X get Y discount                                        |

### Draft Orders

| Tool                     | Description                                                  |
| ------------------------ | ------------------------------------------------------------ |
| **Search Draft Orders**  | Search draft orders                                          |
| **Create Draft Order**   | Create a draft order for phone sales, custom pricing, or B2B |
| **Complete Draft Order** | Complete a draft order, converting it into a real order      |
| **Delete Draft Order**   | Delete an incomplete draft order                             |

### Content

| Tool             | Description                                   |
| ---------------- | --------------------------------------------- |
| **Search Pages** | Search store pages                            |
| **Create Page**  | Create a new page in the online store         |
| **Update Page**  | Update a page's content, title, or visibility |
| **Delete Page**  | Delete a page from the online store           |

### Blogs & Articles

| Tool                 | Description                                                          |
| -------------------- | -------------------------------------------------------------------- |
| **Search Blogs**     | Search blogs in the online store                                     |
| **Create Blog**      | Create a new blog in the online store                                |
| **Search Articles**  | Search blog posts (articles) across the store's blogs                |
| **Create Article**   | Create a new blog post (article) in a blog                           |
| **Update Article**   | Update a blog post's content, title, SEO, image, tags, or visibility |
| **Delete Article**   | Delete a blog post (article)                                         |
| **Search Comments**  | List comments on blog posts, store-wide or for a single article      |
| **Moderate Comment** | Approve, mark as spam/not spam, or delete a blog comment             |

### Metafields

| Tool                 | Description                                |
| -------------------- | ------------------------------------------ |
| **Get Metafields**   | Get metafields for a specific resource     |
| **Set Metafield**    | Create or update a metafield on a resource |
| **Delete Metafield** | Delete a metafield                         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Search products:**

```text theme={"dark"}
Find all products with "sneaker" in the title that are in stock
```

**Manage inventory:**

```text theme={"dark"}
Show me inventory levels for SKU "BLU-SHIRT-M" across all locations
```

**Process orders:**

```text theme={"dark"}
Search for unfulfilled orders from the last 7 days
```

**Customer management:**

```text theme={"dark"}
Find all customers tagged "VIP" and show their order history
```

**Create a discount:**

```text theme={"dark"}
Create a 20% off discount code "SUMMER20" for all products
```

**Fulfill an order:**

```text theme={"dark"}
Create a fulfillment for order #1234 with tracking number "1Z999AA10123456784"
```

**Manage collections:**

```text theme={"dark"}
Add the new fall products to the "Seasonal" collection
```

**Draft orders:**

```text theme={"dark"}
Create a draft order for 2x "Premium Widget" at a custom price of $45 each
```

## Troubleshooting

| Issue                            | Solution                                                                                                                            |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific product IDs, order numbers, or customer emails                                                                         |
| Action not completing            | Check that you've authenticated and your Shopify app has the required permissions                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., searching first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                 |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Cancel all unfulfilled orders older than 30 days" will search for matching orders first, then cancel each one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Shopify MCP server](https://www.gumloop.com/mcp/shopify) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
