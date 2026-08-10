# Framework Proxy Compatibility

`build:compile` owns one proxy profile per immutable Build ID. The default
`--proxy-profile=ray` retains the compatibility path. The opt-in
`--proxy-profile=framework` path compiles attributed Definitions through the
Framework proxy generator and wires each generated Definition to the Framework
runtime invocation seam.

Both profiles publish one common
`proxy-profiles/<build-id>-<content-hash>/manifest.json` unit beside the dumped
Container. The manifest binds the Build ID, selected profile, canonical content
hash, and either the exact Ray proxy file inventory or the matching
`framework-proxies/<build-id>-<input-hash>/` directory and manifest hash. A
release must not replace either immutable directory under an existing identity.

The dumped Container invokes `ProxyProfileArtifactLoader` first. It validates
the common unit path, Build ID, profile, hash, inventory, symlink boundary, and
declared class identity before requiring Ray files. For the Framework profile,
the same loader validates the referenced sibling directory and delegates to
`FrameworkProxyProfileLoader`, which validates the Framework manifest, file
hashes, and class map before loading generated code. A mixed profile, a partial
unit, or a cross-Build reference fails before proxy execution.

The compatibility profile has two Legacy Ray 2.20.0-only signature exceptions:
`never` compilation on PHP 8.5 and extra named variadic forwarding. The
Framework generator supports both and neither exception permits an unproxied
fallback. Their bounded Ray evidence remains only until P21-007 removes the Ray
profile and its compatibility fixtures.

Rollback selects the previous complete release tree: Container,
Operation／HTTP／Frontend／Command manifests, common Profile Unit, and the
referenced Framework Unit when present. HTTP, CLI, and Worker processes must all
start from that same recorded Build ID. A new build may be prepared for the next
rollout, but do not rebuild the same Build ID or point an existing Container at
an artifact from another release.
