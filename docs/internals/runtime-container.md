# Runtime Container

BlackOps uses PSR-11 as the runtime container boundary and Symfony DependencyInjection as the standard build implementation.

The internal runtime container compiler creates a Symfony `ContainerBuilder`, lets bootstrap code register services, compiles the builder, and returns it through the PSR-11 `ContainerInterface`.

The container is used at framework composition boundaries such as handler resolution and console command registration. Operation handlers receive their own dependencies through constructor injection; the container is not passed into handlers, envelopes, or domain services.

The first compile slice supports dynamic development and CI verification. PHP container dumping, public service provider APIs, config loading, and production bootstrap are left to later tasks.
