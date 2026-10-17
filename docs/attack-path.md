# AegisDock Learner Guide

## Scenario

You are investigating a suspicious release event in AegisDock, a fictional smart-port logistics platform. An operator reports that a container was released even though their role should not permit it.

The application, registry simulator, database, and terminal controller are all local training services. The simulated release changes database state only.

## Scope and rules

- Work only against your local AegisDock Compose environment.
- Use the provided fake accounts and training data.
- Do not connect the lab to a real registry, cloud service, port, PLC, SCADA system, or terminal controller.
- Do not add real credentials to the project.
- Record the requests, responses, and audit evidence that support each finding.
- Do not skip directly to the instructor solution; investigate the application and artifacts first.

## Objectives

1. Map the frontend, API, registry simulator, and audit surface.
2. Establish normal behavior for operator and supervisor roles.
3. Discover and obtain the exposed local training image.
4. Inspect Docker image history and layers.
5. Identify the cryptographic construction used for authorization artifacts.
6. Explain which security properties the construction does and does not provide.
7. Demonstrate the authorization impact in the local simulation.
8. Reconstruct the event timeline from audit records.
9. Run the secure profile and repeat the relevant tests.
10. Document how each remediation breaks the original chain.

## Suggested investigation notes

For each stage, record:

- Endpoint or artifact discovered
- Request and response status
- Relevant HTTP headers
- Image/tag and layer evidence
- Cryptographic algorithm and metadata
- Role used and expected permission
- Container state before and after the operation
- Audit events and timestamps
- Remediation and regression-test result

## Local entry points

- Dashboard: http://localhost:8080
- API health: http://localhost:8080/api/health
- Registry metadata: http://localhost:8080/registry/images
- Audit service: http://localhost:8080/audit/logs

Use `make up` to build and start the vulnerable profile, then `make seed` to load the training data.
