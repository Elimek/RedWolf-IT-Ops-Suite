# Architecture Decision Record (ADR)

## ADR-001: PHP 8.2 + MySQL 8.0 Stack
- **Decision**: Use PHP 8.2 FPM with MySQL 8.0
- **Rationale**: Matches the job description requirement for PHP + MySQL skills
- **Alternatives Considered**: Laravel (rejected - zero dependency requirement), Python (rejected - does not demonstrate PHP competency)

## ADR-002: Zero External Dependencies
- **Decision**: No Composer, NPM, or external package managers
- **Rationale**: Demonstrates ability to write production code from scratch; reduces deployment complexity
- **Trade-off**: More manual code, but full control and understanding

## ADR-003: Docker Compose for Orchestration
- **Decision**: Single docker-compose.yml for all services
- **Rationale**: Easy deployment, consistent environments, interview-friendly
- **Components**: Nginx, PHP-FPM, MySQL 8.0, phpMyAdmin, Ollama

## ADR-004: Local AI with Ollama
- **Decision**: Use Ollama for on-premise LLM inference
- **Rationale**: Privacy-first (no data leaves the network), zero API cost, demonstrates Linux + AI integration skills
- **Model**: qwen2.5:7b (good balance of quality and speed)

## ADR-005: Modular Chunk Architecture
- **Decision**: Each module (Magento Lite, Monitoring, AI, Office Tools) is independently deployable
- **Rationale**: Shows project management skills, allows incremental development and testing
- **Deployment Order**: Core (DB) -> Monitoring -> AI -> Office Tools
