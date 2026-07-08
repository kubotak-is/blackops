# HTTP API Slice

The HTTP API slice maps PSR-7 requests to Operations through a small PSR-15 request handler.

The first supported path is API-only:

- exact route matching by HTTP method and path
- minimal `{name}` path parameter matching
- OperationValue binding from explicit path, query, header, and body attributes
- same-name JSON body binding for constructor parameters without a binding attribute
- Inline dispatch through the public Dispatcher port
- JSON response for non-empty Outcomes
- HTTP 204 for EmptyOutcome
- stable JSON rejection responses

`GET` and `HEAD` requests with a body are rejected before dispatch. HTML rendering, frontend client generation, advanced route priority rules, authentication middleware, and manifest file generation are intentionally outside this first slice.

The `GET /welcome` vertical slice demonstrates the full path from HTTP request to Operation Handler and PostgreSQL Canonical Journal persistence.

Route compilation can also produce an in-memory manifest array with route and operation metadata. The current manifest is a runtime structure only; PHP file generation and loading are intentionally left to a later compiler task.
