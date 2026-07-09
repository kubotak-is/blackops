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

The manifest file boundary writes that in-memory structure as a PHP array file. The writer first writes a temporary file in the target directory, validates that the file can be loaded, and then renames it into place. The loader rejects missing files and files that do not return the expected manifest array shape.

The HTTP manifest dump command is a thin Symfony Console boundary around the route compiler and manifest file writer. It receives an already-built operation registry and a definition list from the application bootstrap, then writes the HTTP manifest to the requested path. Discovery, operation providers, and container compilation remain separate build steps.

The internal HTTP manifest compile command reads operation providers from a PHP config file, compiles the operation registry, creates no-argument operation definition instances, and writes the HTTP manifest. Runtime container compilation and Composer package discovery remain separate build steps.

The internal build artifacts command can generate the HTTP manifest together with the operation manifest and runtime container file when both operation provider config and service provider config are available.
