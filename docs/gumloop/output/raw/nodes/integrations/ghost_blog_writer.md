> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Ghost Blog Writer

## Setup

This node requires a Ghost Admin API Key, and a Ghost Admin API URL.

* Go to [https://ghost.org/](https://ghost.org/), and log in with your Ghost site.
* On the dashboard, in the left menu, near the bottom, click on the **Settings** cog.
* Under the **Advanced** section, click on **Integrations**.
* Click on **Add custom integration**, name it, and click Add.
* Now you can see the **Ghost Admin API Key** and the **Ghost Admin API URL**.

<video width="600" controls>
  <source src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/ghost_admin_demo.mp4?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=02a2b2a3f96184e2392b5d2c31ef1d56" type="video/mp4" data-path="images/ghost_admin_demo.mp4" />
</video>

For more details on how to obtain your Ghost Admin API URL and API key, refer to the [Ghost Admin API documentation](https://ghost.org/docs/admin-api/).

## Authentication

* **API Key**: Enter the Ghost Admin API Key on [Gumloop](https://www.gumloop.com/profile#Credentials)

## Node Inputs

* **Title**: This is a string value where you enter the title of the blog post. For example, "My First Blog".
* **HTML Content**: Here, you enter the HTML content of the blog post. This could include headings, paragraphs, images, and other HTML elements.
* **Ghost Admin API URL**: This is the URL of your Ghost Admin API. It should be in the format `https://your-site.ghost.io`.

# Node Functionality

## When To Use

The "Ghost Blog Writer" node is a powerful tool for automating the creation of draft blog posts on a Ghost platform. It can be used in various scenarios, such as:

* Automatically generating blog posts from other content sources.
* Creating drafts for review before publishing.
* Integrating with other workflows to streamline content creation.

By using this node, you can save time and ensure consistency in your blog posts, reducing the manual effort required to create and manage content on your Ghost site.

## Example Usage

Imagine you have a content management system that generates HTML content for blog posts. You can use the "Ghost Blog Writer" node to automatically create draft posts on your Ghost site without manually copying and pasting the content.

By integrating this node into your workflow, you can streamline the process of publishing new content, ensuring that your blog is always up-to-date with the latest information.
