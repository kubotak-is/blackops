# HTTP API Slice

The HTTP API slice maps PSR-7 requests to Operations through a small PSR-15 request handler.

The first supported path is API-only:

- FastRoute matching by HTTP method and static or dynamic path
- `{name}` path parameter matching with decoded values
- OperationValue binding from explicit path, query, header, and body attributes
- same-name JSON body binding for constructor parameters without a binding attribute
- Inline dispatch through the public Dispatcher port
- JSON response for non-empty Outcomes
- HTTP 204 for EmptyOutcome
- stable JSON rejection responses

`GET` and `HEAD` requests with a body are rejected before dispatch. Unknown routes and method-not-allowed results both retain the existing HTTP 404 response. HTML rendering, frontend client generation, authentication middleware, and multiple Route attributes remain outside this slice.

The `GET /welcome` vertical slice demonstrates the full path from HTTP request to Operation Handler and PostgreSQL Canonical Journal persistence.

Route compilation produces route metadata, operation metadata, and FastRoute GroupCountBased Dispatcher Data. The FastRoute handler stored in Dispatcher Data is the Operation Type ID, which the runtime route registry resolves to immutable operation route metadata.

The HTTP manifest file boundary writes a versioned PHP array artifact. HTTP Manifest Schema Version `2` adds the Compile済みDispatcher Data to the payload. The payload contains arrays, scalar metadata, and class names only; FastRoute objects and closures are never persisted. The writer first writes a temporary file in the target directory, validates that the file can be loaded, and then renames it into place.

The loader validates the Dispatcher Data structure, HTTP methods, static paths, dynamic regex chunks, route maps, variable names, and the complete set of Operation Type ID handlers. Missing, malformed, or route-mismatched Dispatcher Data fails startup. Production does not rebuild routes or fall back to Attribute discovery when validation fails.

FastRoute compilation is a build responsibility. Duplicate method/path pairs and variable routes that compile to the same matching regex are rejected before the manifest is written. Runtime request matching constructs FastRoute's dispatcher directly from the loaded array and does not inspect Route attributes or compile route expressions.

The HTTP manifest dump command is a thin Symfony Console boundary around the route compiler and manifest file writer. It receives an already-built operation registry and a definition list from the application bootstrap, then writes the HTTP manifest to the requested path. Discovery, operation providers, and container compilation remain separate build steps.

The internal HTTP manifest compile command reads operation providers from a PHP config file, compiles the operation registry, creates no-argument operation definition instances, and writes the HTTP manifest. Runtime container compilation and Composer package discovery remain separate build steps.

The internal build artifacts command can generate the HTTP manifest together with the operation manifest and runtime container file when both operation provider config and service provider config are available. The HTTP manifest and operation manifest retain the same Application Build ID even though their schema versions evolve independently.
