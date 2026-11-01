> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Flow Terminated Due to Excess Memory Consumption

Each automation run in Gumloop operates within a memory limit. If your flow exceeds this limit during execution, it will be automatically terminated and you will see the following error in your run log:

```text theme={"dark"}
Flow terminated due to excess memory consumption.
```

This page explains why this happens and how to fix it.

## Why Does This Happen?

When a flow runs, all of its processing, including any concurrent operations like loop mode iterations or subflow executions, shares the same memory allocation. If the combined memory usage of your flow exceeds the allowed threshold, the run is terminated to protect system stability.

When nodes run in [Loop Mode](/common_errors/loop_mode), multiple list items are processed concurrently. The number of items processed at the same time depends on your subscription tier:

| Plan       | Concurrent Items |
| ---------- | ---------------- |
| Pro        | 15               |
| Enterprise | Custom           |

While this concurrency level is manageable on its own, **the most common cause of memory errors is nested concurrency**: for example, a [subflow](/core-concepts/subflows) running in loop mode that itself contains another subflow or loop mode node. In this case, the concurrency multiplies: a Pro-tier flow with a loop mode subflow containing another loop mode step could have up to 15 × 15 = 225 concurrent operations, all sharing the same memory allocation.

Other common causes include:

1. **Deeply nested subflow chains:** Each level of nesting multiplies the number of concurrent operations. Even two levels of loop mode nesting can quickly exceed memory limits.

2. **Large data payloads:** In rare cases, processing very large files, long text content, or large API responses can exceed the memory limit even without heavy concurrency. If individual items in your flow carry large payloads, even a small number of concurrent operations may be enough to trigger this error.

## How to Fix It

### 1. Reduce Concurrent Processing by Batching Lists

If your flow processes a large list through nested subflows or loop mode nodes, reduce the size of the input list at each level. This limits the number of concurrent operations and lowers peak memory usage.

**Example:** If you have a subflow running in loop mode that itself contains a loop mode node, reduce the input list size so that fewer items are processed at each level. You can use the [List Trimmer](/nodes/list_operations/list_trimmer) node to slice your list into smaller chunks and process each batch sequentially rather than all at once.

### 2. Use the API to Distribute Processing Across Separate Runs

Instead of processing all items within a single flow run, use the [Gumloop API](/api-reference/running-an-automation/start-automation) to trigger separate runs for each batch. Each API-triggered run is handled independently, so the memory usage of one run does not affect the others.

You can trigger these separate runs using:

* A [Custom Node](/nodes/custom_node_details) within Gumloop, which has built-in secret management for securely storing your API key. Your custom node can split the input list into batches and call the Gumloop API for each batch.
* An external Python script running on your own infrastructure that splits your data and triggers a separate Gumloop API run for each batch.

Each API-triggered run processes a smaller portion of the data independently, so no single run needs to hold all the data in memory at once.

### 3. Reduce Payload Size

In rare cases, this error can occur even without deeply nested concurrency if individual items in your flow are very large. If your flow handles large files or data objects, consider:

* Filtering or trimming data before processing (e.g., extract only the fields you need from a large JSON response).
* Processing files one at a time rather than in bulk.
* Using pagination when reading from data sources that support it.

If you've reduced your payload size and are still hitting this error, please reach out to [support@gumloop.com](mailto:support@gumloop.com) so we can help diagnose your specific flow.

## Summary

| Approach                             | When to Use                                                                              |
| ------------------------------------ | ---------------------------------------------------------------------------------------- |
| Batch your lists                     | You have nested loop mode or subflow concurrency multiplying memory usage                |
| Use the API to trigger separate runs | You need to process a very large dataset and batching within a single flow is not enough |
| Reduce payload size                  | Individual items in your flow are very large (big files, long text, etc.)                |

**Still stuck?** [Reach out to us](https://portal.usepylon.com/gumloop/forms/help) and we'll help you optimize your flow.
