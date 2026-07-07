# HTTP API Slice

The HTTP API slice maps PSR-7 requests to Operations through a small PSR-15 request handler.

The first supported path is API-only:

- exact route matching by HTTP method and path
- OperationValue binding from query parameters or an empty constructor
- Inline dispatch through the public Dispatcher port
- JSON response for non-empty Outcomes
- HTTP 204 for EmptyOutcome
- stable JSON rejection responses

`GET` and `HEAD` requests with a body are rejected before dispatch. HTML rendering, frontend client generation, dynamic path parameters, authentication middleware, and manifest generation are intentionally outside this first slice.

The `GET /welcome` vertical slice demonstrates the full path from HTTP request to Operation Handler and PostgreSQL Canonical Journal persistence.
