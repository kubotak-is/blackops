# Framework Proxy Compatibility

`build:compile` owns one Framework proxy profile per immutable Build ID. The
Framework generator compiles attributed Definitions and wires each generated
Definition to the Framework runtime invocation seam.

The build publishes one common
`proxy-profiles/<build-id>-<content-hash>/manifest.json` unit beside the dumped
Container. The manifest binds the Build ID, Framework profile, canonical content
hash, and the matching
`framework-proxies/<build-id>-<input-hash>/` directory and manifest hash. A
release must not replace either immutable directory under an existing identity.

The dumped Container invokes `ProxyProfileArtifactLoader` first. It validates
the common unit path, Build ID, Framework profile, hash, inventory, and symlink
boundary before delegating to `FrameworkProxyProfileLoader`, which validates the
Framework manifest, file hashes, and class map before loading generated code.
A partial unit or cross-Build reference fails before proxy execution. Runtime
does not scan source or fall back to another profile.

The Framework Signature Matrix includes `never` and named variadic forwarding.

Rollback selects the previous complete release tree: Container,
Operation／HTTP／Frontend／Command manifests, common Profile Unit, and the
referenced Framework Unit when present. HTTP, CLI, and Worker processes must all
start from that same recorded Build ID. A new build may be prepared for the next
rollout, but do not rebuild the same Build ID or point an existing Container at
an artifact from another release.
