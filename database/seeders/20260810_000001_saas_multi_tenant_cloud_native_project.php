<?php

declare(strict_types=1);

if (!function_exists('saas_multi_tenant_cloud_native_plan_blueprints')) {
    function saas_multi_tenant_cloud_native_plan_blueprints(): array
    {
        $phases = [
            [
                'title' => 'Iniciacao e planejamento do produto',
                'role' => 'Gerente de Projeto',
                'goal' => 'Estabelecer visao, escopo, governanca, riscos e baseline inicial do produto.',
                'milestone' => 'Kick-off executivo aprovado',
                'epics' => [
                    [
                        'title' => 'Visao, escopo e governanca',
                        'role' => 'Product Manager',
                        'goal' => 'Definir a direcao de produto e as regras de decisao.',
                        'tasks' => [
                            ['Consolidar visao do produto e proposta de valor', 'Product Manager', 2],
                            ['Definir personas, empresas-alvo e jornadas criticas', 'Product Designer', 2],
                            ['Criar canvas de riscos, premissas e restricoes', 'Gerente de Projeto', 1],
                        ],
                    ],
                    [
                        'title' => 'Planejamento executivo e WBS',
                        'role' => 'Gerente de Projeto',
                        'goal' => 'Transformar o produto em um plano acompanhavel por entregas.',
                        'tasks' => [
                            ['Estruturar roadmap macro e trilhas de entrega', 'Gerente de Projeto', 2],
                            ['Definir WBS, marcos, baseline e criterios de aceite', 'Gerente de Projeto', 2],
                            ['Alinhar cadencia de rituais, reports e tomada de decisao', 'Scrum Master', 1],
                        ],
                    ],
                    [
                        'title' => 'Ambiente de colaboracao',
                        'role' => 'Tech Lead',
                        'goal' => 'Preparar o fluxo de trabalho tecnico e de acompanhamento.',
                        'tasks' => [
                            ['Configurar repositorios, convencoes e protecoes de branch', 'Tech Lead', 1],
                            ['Criar templates de issues, pull requests e ADRs', 'Tech Lead', 1],
                            ['Preparar backlog inicial com epicos e historias', 'Product Manager', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Fundacao e arquitetura',
                'role' => 'Arquiteto de Software',
                'goal' => 'Definir a arquitetura base em FastAPI com separacao clara de responsabilidades.',
                'milestone' => 'Arquitetura base aprovada',
                'epics' => [
                    [
                        'title' => 'Clean Architecture e DDD',
                        'role' => 'Arquiteto de Software',
                        'goal' => 'Organizar boundaries, camadas e linguagem do dominio.',
                        'tasks' => [
                            ['Definir boundaries, contextos e linguagem ubiqua', 'Arquiteto de Software', 2],
                            ['Criar estrutura backend FastAPI por camadas', 'Tech Lead Backend', 2],
                            ['Registrar ADRs de arquitetura e padroes de codigo', 'Arquiteto de Software', 1],
                        ],
                    ],
                    [
                        'title' => 'Runtime Python e configuracao',
                        'role' => 'Tech Lead Backend',
                        'goal' => 'Padronizar runtime, configuracao e inicializacao da API.',
                        'tasks' => [
                            ['Padronizar Python 3.13+, pyproject e ambientes locais', 'Tech Lead Backend', 2],
                            ['Configurar Pydantic Settings v2 para ambientes', 'Engenheiro Backend', 1],
                            ['Implementar bootstrap da aplicacao FastAPI', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Contratos transversais da API',
                        'role' => 'Tech Lead Backend',
                        'goal' => 'Definir padroes de resposta, erro e contexto por requisicao.',
                        'tasks' => [
                            ['Definir formato de erros e envelopes de resposta', 'Tech Lead Backend', 1],
                            ['Criar middlewares de request id e contexto', 'Engenheiro Backend', 2],
                            ['Estabelecer padrao de paginacao, filtros e ordenacao', 'Engenheiro Backend', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Banco de dados e migrations',
                'role' => 'DBA / Engenheiro Backend',
                'goal' => 'Preparar PostgreSQL, SQLAlchemy 2 e Alembic para evolucao segura do schema.',
                'milestone' => 'Persistencia base validada',
                'epics' => [
                    [
                        'title' => 'PostgreSQL e extensoes',
                        'role' => 'DBA',
                        'goal' => 'Configurar banco, extensoes e padroes fisicos.',
                        'tasks' => [
                            ['Provisionar PostgreSQL com uuid, pg_trgm e pgcrypto', 'DBA', 2],
                            ['Definir schemas, naming conventions e ownership', 'DBA', 1],
                            ['Configurar pooling e timeouts de conexao', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'SQLAlchemy 2 e Alembic',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Implementar base de persistencia async e versionamento.',
                        'tasks' => [
                            ['Criar base declarativa, session async e Unit of Work', 'Engenheiro Backend', 2],
                            ['Configurar Alembic async e revisoes iniciais', 'Engenheiro Backend', 2],
                            ['Implementar convencoes de constraints e indices', 'DBA', 1],
                        ],
                    ],
                    [
                        'title' => 'Modelo base e auditoria',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Criar modelos compartilhados para rastreabilidade e ciclo de vida.',
                        'tasks' => [
                            ['Criar mixins de UUID, timestamps e soft delete', 'Engenheiro Backend', 1],
                            ['Modelar tabela de auditoria e eventos de dominio', 'Engenheiro Backend', 2],
                            ['Validar migrations em ambiente limpo e incremental', 'Analista de QA', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Autenticacao, OAuth2 e MFA',
                'role' => 'Engenheiro de Seguranca',
                'goal' => 'Construir identidade segura com JWT, refresh token, OAuth2 e MFA.',
                'milestone' => 'Identidade segura pronta',
                'epics' => [
                    [
                        'title' => 'Identidade e credenciais',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Implementar usuarios, senhas e fluxo basico de login.',
                        'tasks' => [
                            ['Implementar cadastro e ativacao de usuarios', 'Engenheiro Backend', 2],
                            ['Criar hashing BCrypt e politica de senha', 'Engenheiro de Seguranca', 1],
                            ['Implementar login OAuth2 password flow', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'JWT e refresh tokens',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Controlar sessoes stateless com rotacao e revogacao.',
                        'tasks' => [
                            ['Criar access token com claims de tenant e permissoes', 'Engenheiro Backend', 2],
                            ['Persistir refresh tokens com rotacao e revogacao', 'Engenheiro Backend', 2],
                            ['Implementar logout, revogacao global e expiracao', 'Engenheiro Backend', 1],
                        ],
                    ],
                    [
                        'title' => 'MFA TOTP e recuperacao',
                        'role' => 'Engenheiro de Seguranca',
                        'goal' => 'Adicionar segundo fator e recuperacao segura.',
                        'tasks' => [
                            ['Configurar TOTP enrollment e QR secret', 'Engenheiro de Seguranca', 2],
                            ['Validar MFA no login e em acoes sensiveis', 'Engenheiro Backend', 2],
                            ['Implementar recuperacao com tokens de uso unico', 'Engenheiro Backend', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Seguranca da aplicacao',
                'role' => 'Engenheiro de Seguranca',
                'goal' => 'Aplicar controles de seguranca HTTP, validacao, rate limit e auditoria.',
                'milestone' => 'Controles de seguranca aplicados',
                'epics' => [
                    [
                        'title' => 'Protecoes HTTP e API',
                        'role' => 'Engenheiro de Seguranca',
                        'goal' => 'Reduzir superficie de ataque nos pontos de entrada.',
                        'tasks' => [
                            ['Configurar CORS restritivo por ambiente', 'Engenheiro Backend', 1],
                            ['Definir CSP, security headers e trusted hosts', 'Engenheiro de Seguranca', 1],
                            ['Implantar rate limit com Redis por tenant e usuario', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Validacao e hardening de entrada',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Evitar entrada invalida, mass assignment e query dinamica insegura.',
                        'tasks' => [
                            ['Criar validadores Pydantic contra mass assignment', 'Engenheiro Backend', 2],
                            ['Sanitizar filtros e ordenar consultas com allowlist', 'Engenheiro Backend', 2],
                            ['Testar protecao contra SQL injection em queries dinamicas', 'Analista de QA', 1],
                        ],
                    ],
                    [
                        'title' => 'Secrets e trilhas de auditoria',
                        'role' => 'Engenheiro de Seguranca',
                        'goal' => 'Padronizar segredos, rastreabilidade e mascaramento.',
                        'tasks' => [
                            ['Padronizar carregamento de secrets por ambiente', 'DevOps Engineer', 1],
                            ['Criar logs de autenticacao e alteracoes sensiveis', 'Engenheiro Backend', 2],
                            ['Definir politica de retencao e mascaramento de dados', 'Engenheiro de Seguranca', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Multi-tenancy e isolamento',
                'role' => 'Arquiteto de Software',
                'goal' => 'Garantir isolamento completo dos dados entre empresas no mesmo runtime.',
                'milestone' => 'Isolamento multi-tenant validado',
                'epics' => [
                    [
                        'title' => 'Estrategia de tenant',
                        'role' => 'Arquiteto de Software',
                        'goal' => 'Definir como o tenant e resolvido e propagado.',
                        'tasks' => [
                            ['Definir modelo shared database com tenant_id obrigatorio', 'Arquiteto de Software', 2],
                            ['Implementar resolucao de tenant por subdominio, header e token', 'Engenheiro Backend', 2],
                            ['Criar contrato de TenantContext por request', 'Engenheiro Backend', 1],
                        ],
                    ],
                    [
                        'title' => 'Isolamento de dados',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Impedir leitura e escrita cross-tenant em todos os fluxos.',
                        'tasks' => [
                            ['Aplicar filtros obrigatorios de tenant nos repositories', 'Engenheiro Backend', 2],
                            ['Criar guards para escrita cross-tenant', 'Engenheiro Backend', 2],
                            ['Testar isolamento entre empresas com fixtures', 'Analista de QA', 2],
                        ],
                    ],
                    [
                        'title' => 'Provisionamento de tenants',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Automatizar onboarding, administracao e ciclo de vida de empresas.',
                        'tasks' => [
                            ['Implementar cadastro de empresas e bootstrap inicial', 'Engenheiro Backend', 2],
                            ['Criar seeds por tenant com roles e usuario admin', 'Engenheiro Backend', 1],
                            ['Preparar suspensao e reativacao de tenant', 'Engenheiro Backend', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RBAC e permissoes',
                'role' => 'Tech Lead Backend',
                'goal' => 'Controlar acesso por papel, permissao, recurso e tenant.',
                'milestone' => 'RBAC operacional',
                'epics' => [
                    [
                        'title' => 'Modelo de autorizacao',
                        'role' => 'Arquiteto de Software',
                        'goal' => 'Criar matriz e modelo de permissoes extensivel.',
                        'tasks' => [
                            ['Modelar roles, permissoes e grants por tenant', 'Arquiteto de Software', 2],
                            ['Definir matriz de acesso por modulo e recurso', 'Product Manager', 2],
                            ['Implementar policy engine simples no service layer', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Enforcement nas APIs',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Aplicar autorizacao nos endpoints e registrar negacoes.',
                        'tasks' => [
                            ['Criar dependencies FastAPI para permissoes', 'Engenheiro Backend', 1],
                            ['Aplicar guards por endpoint e operacao', 'Engenheiro Backend', 2],
                            ['Padronizar respostas 403 e logs de negacao', 'Engenheiro Backend', 1],
                        ],
                    ],
                    [
                        'title' => 'Administracao de acessos',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Permitir gestao de roles, membros e convites por tenant.',
                        'tasks' => [
                            ['Criar endpoints CRUD de roles e membros', 'Engenheiro Backend', 2],
                            ['Implementar convite de usuarios e aceite', 'Engenheiro Backend', 2],
                            ['Auditar alteracoes de permissao e membership', 'Engenheiro Backend', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'APIs de negocio',
                'role' => 'Tech Lead Backend',
                'goal' => 'Entregar APIs de dominio com contratos claros, busca, limites e relatorios.',
                'milestone' => 'APIs de negocio completas',
                'epics' => [
                    [
                        'title' => 'Organizacoes e contas',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Criar APIs de estruturas organizacionais por tenant.',
                        'tasks' => [
                            ['Criar CRUD de empresas, unidades e perfis', 'Engenheiro Backend', 2],
                            ['Implementar filtros, busca pg_trgm e paginacao', 'Engenheiro Backend', 2],
                            ['Expor schemas OpenAPI e exemplos de payload', 'Engenheiro Backend', 1],
                        ],
                    ],
                    [
                        'title' => 'Planos, assinaturas e limites',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Modelar planos de uso e aplicacao de limites comerciais.',
                        'tasks' => [
                            ['Modelar planos, limites e add-ons', 'Product Manager', 2],
                            ['Implementar verificacao de limites por tenant', 'Engenheiro Backend', 2],
                            ['Criar eventos de billing e webhooks internos', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Modulos operacionais',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Disponibilizar recursos de negocio representativos para portfolio.',
                        'tasks' => [
                            ['Criar APIs de clientes, projetos e tarefas', 'Engenheiro Backend', 2],
                            ['Implementar anexos com metadados e storage S3-ready', 'Engenheiro Backend', 2],
                            ['Consolidar relatorios operacionais por tenant', 'Engenheiro Backend', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Filas e background jobs',
                'role' => 'Engenheiro Backend',
                'goal' => 'Processar emails, auditoria, notificacoes e cargas pesadas fora do request.',
                'milestone' => 'Workers e filas operacionais',
                'epics' => [
                    [
                        'title' => 'Celery e Redis broker',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Configurar infraestrutura de filas e workers.',
                        'tasks' => [
                            ['Configurar Celery app, filas e serializacao JSON', 'Engenheiro Backend', 2],
                            ['Separar workers por prioridade e dominio', 'Engenheiro Backend', 1],
                            ['Criar health checks de workers e broker', 'DevOps Engineer', 1],
                        ],
                    ],
                    [
                        'title' => 'Jobs transacionais',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Garantir execucao confiavel de eventos apos commit.',
                        'tasks' => [
                            ['Implementar envio de emails assincrono', 'Engenheiro Backend', 2],
                            ['Criar outbox para eventos pos-commit', 'Engenheiro Backend', 2],
                            ['Processar notificacoes e webhooks com retry', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Jobs pesados e scheduler',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Executar processamento pesado com idempotencia e agendamento.',
                        'tasks' => [
                            ['Implementar exportacao e processamento em lote', 'Engenheiro Backend', 2],
                            ['Configurar Celery Beat para rotinas periodicas', 'Engenheiro Backend', 1],
                            ['Definir idempotencia, deduplicacao e DLQ logica', 'Tech Lead Backend', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Observabilidade',
                'role' => 'SRE / DevOps Engineer',
                'goal' => 'Instrumentar logs, metricas, dashboards, alertas e tracing distribuido.',
                'milestone' => 'Observabilidade ponta a ponta ativa',
                'epics' => [
                    [
                        'title' => 'Logs estruturados',
                        'role' => 'SRE',
                        'goal' => 'Padronizar logs JSON com contexto de requisicao e tenant.',
                        'tasks' => [
                            ['Definir formato JSON com request_id, tenant_id e user_id', 'SRE', 1],
                            ['Instrumentar logs de erros e latencia por endpoint', 'Engenheiro Backend', 2],
                            ['Configurar mascaramento de PII nos logs', 'Engenheiro de Seguranca', 1],
                        ],
                    ],
                    [
                        'title' => 'Prometheus e Grafana',
                        'role' => 'SRE',
                        'goal' => 'Monitorar API, banco, cache e workers por SLO.',
                        'tasks' => [
                            ['Expor metrics endpoint com FastAPI', 'Engenheiro Backend', 1],
                            ['Criar dashboards Grafana de API, DB, Redis e Celery', 'SRE', 2],
                            ['Definir alertas de SLO, saturacao e filas', 'SRE', 2],
                        ],
                    ],
                    [
                        'title' => 'Tracing OpenTelemetry',
                        'role' => 'SRE',
                        'goal' => 'Rastrear requisicoes entre API, banco, Redis e workers.',
                        'tasks' => [
                            ['Instrumentar FastAPI, SQLAlchemy, Redis e Celery', 'SRE', 2],
                            ['Propagar contexto entre API e workers', 'Engenheiro Backend', 2],
                            ['Validar spans em ambiente de staging', 'SRE', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Testes e qualidade',
                'role' => 'QA Lead',
                'goal' => 'Garantir confiabilidade com testes unitarios, integracao, contrato e fluxos criticos.',
                'milestone' => 'Suite de testes confiavel',
                'epics' => [
                    [
                        'title' => 'Testes unitarios',
                        'role' => 'QA Lead',
                        'goal' => 'Cobrir regras de dominio, services, policies e validators.',
                        'tasks' => [
                            ['Configurar Pytest, pytest-asyncio e factories', 'Analista de QA', 2],
                            ['Cobrir services, policies e validators', 'Engenheiro Backend', 2],
                            ['Medir coverage por modulo e thresholds iniciais', 'QA Lead', 1],
                        ],
                    ],
                    [
                        'title' => 'Testes de integracao',
                        'role' => 'Analista de QA',
                        'goal' => 'Validar persistencia, cache, tenant e autenticacao com dependencias reais.',
                        'tasks' => [
                            ['Subir PostgreSQL e Redis com Testcontainers', 'Analista de QA', 2],
                            ['Testar repositories, Unit of Work e migrations', 'Engenheiro Backend', 2],
                            ['Validar fluxos multi-tenant e autenticacao', 'Analista de QA', 2],
                        ],
                    ],
                    [
                        'title' => 'Testes de contrato e E2E',
                        'role' => 'Analista de QA',
                        'goal' => 'Proteger contratos OpenAPI e jornadas criticas.',
                        'tasks' => [
                            ['Criar testes de contrato OpenAPI', 'Analista de QA', 2],
                            ['Automatizar fluxos criticos de login e RBAC', 'Analista de QA', 2],
                            ['Executar suite completa no pipeline', 'QA Lead', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Qualidade de codigo e pre-commit',
                'role' => 'Tech Lead Backend',
                'goal' => 'Padronizar lint, formatacao, tipagem e checks antes do commit.',
                'milestone' => 'Quality gate local habilitado',
                'epics' => [
                    [
                        'title' => 'Ruff e Black',
                        'role' => 'Tech Lead Backend',
                        'goal' => 'Automatizar lint e formatacao do codigo Python.',
                        'tasks' => [
                            ['Configurar Ruff lint e format com regras do projeto', 'Tech Lead Backend', 1],
                            ['Padronizar Black e import sorting via Ruff', 'Engenheiro Backend', 1],
                            ['Ajustar codigo inicial aos checks', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'MyPy e tipagem',
                        'role' => 'Tech Lead Backend',
                        'goal' => 'Aumentar previsibilidade de contratos internos.',
                        'tasks' => [
                            ['Configurar MyPy estrito por camadas', 'Tech Lead Backend', 1],
                            ['Tipar repositories, services e dependencies', 'Engenheiro Backend', 2],
                            ['Corrigir pontos de Any e definir protocolos', 'Engenheiro Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Hooks e convencoes',
                        'role' => 'Tech Lead Backend',
                        'goal' => 'Bloquear problemas comuns antes de chegar ao repositorio remoto.',
                        'tasks' => [
                            ['Configurar pre-commit com lint, format e secrets scan', 'DevOps Engineer', 1],
                            ['Criar conventional commits e templates de PR', 'Tech Lead Backend', 1],
                            ['Documentar Definition of Done tecnico', 'Tech Lead Backend', 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'CI/CD com GitHub Actions',
                'role' => 'DevOps Engineer',
                'goal' => 'Automatizar verificacao, build, publicacao e promocao de ambientes.',
                'milestone' => 'Pipeline CI/CD operando',
                'epics' => [
                    [
                        'title' => 'Pipeline de verificacao',
                        'role' => 'DevOps Engineer',
                        'goal' => 'Executar qualidade e testes a cada pull request.',
                        'tasks' => [
                            ['Criar workflow de lint, typecheck e testes', 'DevOps Engineer', 2],
                            ['Cachear dependencias Python e Docker layers', 'DevOps Engineer', 1],
                            ['Publicar relatorios de coverage e artifacts', 'DevOps Engineer', 1],
                        ],
                    ],
                    [
                        'title' => 'Build e versionamento',
                        'role' => 'DevOps Engineer',
                        'goal' => 'Produzir imagens versionadas e auditaveis.',
                        'tasks' => [
                            ['Criar build de imagem Docker com tags semanticas', 'DevOps Engineer', 2],
                            ['Gerar SBOM e assinatura de imagem', 'Engenheiro de Seguranca', 2],
                            ['Publicar imagens em registry controlado', 'DevOps Engineer', 1],
                        ],
                    ],
                    [
                        'title' => 'Deploy automatizado',
                        'role' => 'DevOps Engineer',
                        'goal' => 'Promover releases com aprovacao, rollback e migrations controladas.',
                        'tasks' => [
                            ['Criar ambientes dev, staging e prod', 'DevOps Engineer', 2],
                            ['Implementar approvals e promocao entre ambientes', 'DevOps Engineer', 2],
                            ['Adicionar rollback por tag e migracoes controladas', 'DevOps Engineer', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Containers, Docker Compose e Kubernetes',
                'role' => 'DevOps Engineer',
                'goal' => 'Empacotar API, workers e dependencias para desenvolvimento e operacao em cluster.',
                'milestone' => 'Aplicacao containerizada e orquestrada',
                'epics' => [
                    [
                        'title' => 'Docker e Compose',
                        'role' => 'DevOps Engineer',
                        'goal' => 'Criar ambiente local reproduzivel.',
                        'tasks' => [
                            ['Criar Dockerfile multi-stage para API', 'DevOps Engineer', 2],
                            ['Montar Compose com API, PostgreSQL, Redis e worker', 'DevOps Engineer', 2],
                            ['Adicionar scripts de bootstrap e health checks', 'DevOps Engineer', 1],
                        ],
                    ],
                    [
                        'title' => 'Kubernetes base',
                        'role' => 'DevOps Engineer',
                        'goal' => 'Definir recursos para API, workers, scheduler e configuracoes.',
                        'tasks' => [
                            ['Criar manifests ou Helm para API, worker e scheduler', 'DevOps Engineer', 2],
                            ['Configurar ConfigMaps, Secrets e probes', 'DevOps Engineer', 2],
                            ['Definir requests, limits e HPA inicial', 'SRE', 2],
                        ],
                    ],
                    [
                        'title' => 'Operacao no cluster',
                        'role' => 'SRE',
                        'goal' => 'Executar deploys previsiveis e seguros no cluster.',
                        'tasks' => [
                            ['Implementar migrations como job controlado', 'DevOps Engineer', 2],
                            ['Configurar ingress, TLS e timeouts', 'SRE', 2],
                            ['Validar deploy rolling update e estrategia blue-green', 'SRE', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'AWS e infraestrutura cloud',
                'role' => 'Cloud Architect',
                'goal' => 'Planejar e preparar AWS com ECS/EKS, RDS, S3, CloudWatch e Secrets Manager.',
                'milestone' => 'Infraestrutura cloud pronta para staging',
                'epics' => [
                    [
                        'title' => 'Rede, contas e seguranca AWS',
                        'role' => 'Cloud Architect',
                        'goal' => 'Definir fundacao de rede, identidade e custos.',
                        'tasks' => [
                            ['Definir contas, VPC, subnets e security groups', 'Cloud Architect', 2],
                            ['Configurar IAM minimo para ECS, EKS, RDS e S3', 'Cloud Architect', 2],
                            ['Padronizar tagging, budgets e controle de custos', 'FinOps / DevOps', 1],
                        ],
                    ],
                    [
                        'title' => 'Servicos gerenciados',
                        'role' => 'Cloud Architect',
                        'goal' => 'Provisionar dados, arquivos, cache e segredos gerenciados.',
                        'tasks' => [
                            ['Provisionar RDS PostgreSQL com backups', 'Cloud Architect', 2],
                            ['Provisionar S3 para arquivos e exports', 'Cloud Architect', 1],
                            ['Configurar Secrets Manager e parametros por ambiente', 'DevOps Engineer', 2],
                        ],
                    ],
                    [
                        'title' => 'Execucao e operacao AWS',
                        'role' => 'SRE',
                        'goal' => 'Operar API e workers com logs, alarmes e escala.',
                        'tasks' => [
                            ['Configurar ECS ou EKS para API e workers', 'Cloud Architect', 3],
                            ['Integrar CloudWatch Logs, Metrics e alarms', 'SRE', 2],
                            ['Definir runbook de backup, restore e rotacao de secrets', 'SRE', 2],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Performance e escalabilidade',
                'role' => 'SRE / Tech Lead Backend',
                'goal' => 'Medir e otimizar consultas, cache, capacidade e comportamento sob carga.',
                'milestone' => 'Metas de performance atingidas',
                'epics' => [
                    [
                        'title' => 'Banco e consultas',
                        'role' => 'DBA',
                        'goal' => 'Reduzir latencia e custo de queries criticas.',
                        'tasks' => [
                            ['Criar indices para filtros e buscas pg_trgm', 'DBA', 2],
                            ['Otimizar N+1 e planos de consulta', 'Engenheiro Backend', 2],
                            ['Adicionar testes de carga focados em queries', 'Analista de QA', 2],
                        ],
                    ],
                    [
                        'title' => 'Cache e rate limits',
                        'role' => 'Engenheiro Backend',
                        'goal' => 'Usar Redis com invalidacao previsivel e metricas de eficiencia.',
                        'tasks' => [
                            ['Implementar cache Redis com chaves por tenant', 'Engenheiro Backend', 2],
                            ['Definir invalidacao por eventos de dominio', 'Engenheiro Backend', 2],
                            ['Medir hit ratio e efeitos em latencia', 'SRE', 1],
                        ],
                    ],
                    [
                        'title' => 'Carga e capacidade',
                        'role' => 'SRE',
                        'goal' => 'Dimensionar API, workers, pool de conexoes e autoscaling.',
                        'tasks' => [
                            ['Criar cenarios de carga para endpoints criticos', 'SRE', 2],
                            ['Definir metas de throughput, p95 e taxa de erro', 'SRE', 1],
                            ['Rodar tuning de workers, pool e autoscaling', 'SRE', 3],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Documentacao, hardening e release',
                'role' => 'Gerente de Projeto',
                'goal' => 'Fechar documentacao, aceitar escopo, endurecer seguranca e publicar a primeira versao.',
                'milestone' => 'Go-live concluido',
                'epics' => [
                    [
                        'title' => 'Documentacao tecnica e produto',
                        'role' => 'Tech Writer / Tech Lead',
                        'goal' => 'Preparar material de desenvolvimento, operacao e portfolio.',
                        'tasks' => [
                            ['Documentar arquitetura, ADRs e diagramas C4', 'Tech Lead Backend', 2],
                            ['Criar guia local de desenvolvimento e padroes de API', 'Tech Writer', 2],
                            ['Preparar material de portfolio e estudo de caso', 'Product Manager', 2],
                        ],
                    ],
                    [
                        'title' => 'Hardening e compliance final',
                        'role' => 'Engenheiro de Seguranca',
                        'goal' => 'Executar revisoes finais de seguranca, segredos e vulnerabilidades.',
                        'tasks' => [
                            ['Executar revisao de seguranca pre-release', 'Engenheiro de Seguranca', 2],
                            ['Validar politicas de secrets, tokens e headers', 'Engenheiro de Seguranca', 1],
                            ['Fechar vulnerabilidades e pendencias criticas', 'Tech Lead Backend', 2],
                        ],
                    ],
                    [
                        'title' => 'Go-live e pos-release',
                        'role' => 'Gerente de Projeto',
                        'goal' => 'Executar release, monitorar primeira janela e planejar evolucao.',
                        'tasks' => [
                            ['Rodar UAT com cenarios multi-tenant', 'Analista de QA', 2],
                            ['Executar plano de release e migracoes', 'DevOps Engineer', 1],
                            ['Monitorar primeira janela e registrar retro', 'SRE', 2],
                        ],
                    ],
                ],
            ],
        ];

        $blueprints = [];
        $add = static function (
            string $code,
            string $name,
            string $description,
            int $level,
            string $status,
            int $progress,
            int $startOffset,
            int $endOffset,
            array $dependsOn = [],
            bool $milestone = false
        ) use (&$blueprints): int {
            $blueprints[] = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'level' => $level,
                'status' => $status,
                'progress' => $progress,
                'start_offset' => $startOffset,
                'end_offset' => $endOffset,
                'duration' => max(1, $endOffset - $startOffset + 1),
                'depends_on' => $dependsOn,
                'milestone' => $milestone,
            ];

            return count($blueprints) - 1;
        };

        $rootIndex = $add(
            'SAAS-MT-001',
            'SaaS Multi-Tenant Cloud-Native',
            'Responsavel: Gerente de Projeto. Objetivo: plataforma SaaS multi-tenant cloud-native em Python 3.13+, FastAPI, PostgreSQL, Redis, Celery, Kubernetes e AWS.',
            0,
            'STATUS_ACTIVE',
            0,
            0,
            0
        );

        $cursor = 0;
        $previousMilestone = null;

        foreach ($phases as $phaseIndex => $phase) {
            $phaseNumber = $phaseIndex + 1;
            $phaseCode = sprintf('SAAS-MT-%02d', $phaseNumber);
            $phaseStart = $cursor;
            $phaseStatus = $phaseNumber === 1 ? 'STATUS_ACTIVE' : 'STATUS_WAITING';
            $phaseDepends = $previousMilestone === null ? [] : [$previousMilestone];
            $phaseRow = $add(
                $phaseCode,
                (string)$phase['title'],
                sprintf('Responsavel: %s. Objetivo: %s', $phase['role'], $phase['goal']),
                1,
                $phaseStatus,
                0,
                $phaseStart,
                $phaseStart,
                $phaseDepends
            );

            $phaseEnd = $phaseStart;
            $lastEpicTaskCodes = [];

            foreach ($phase['epics'] as $epicIndex => $epic) {
                $epicNumber = $epicIndex + 1;
                $epicCode = sprintf('%s.%02d', $phaseCode, $epicNumber);
                $epicStatus = $phaseStatus;
                $epicDepends = $previousMilestone === null ? [] : [$previousMilestone];
                $epicRow = $add(
                    $epicCode,
                    (string)$epic['title'],
                    sprintf('Responsavel: %s. Objetivo: %s', $epic['role'], $epic['goal']),
                    2,
                    $epicStatus,
                    0,
                    $phaseStart,
                    $phaseStart,
                    $epicDepends
                );

                $taskCursor = $phaseStart;
                $previousTaskCode = $previousMilestone;
                $lastTaskCode = null;

                foreach ($epic['tasks'] as $taskIndex => $task) {
                    [$taskName, $taskRole, $duration] = $task;
                    $taskNumber = $taskIndex + 1;
                    $taskCode = sprintf('%s.%02d', $epicCode, $taskNumber);
                    $taskStart = $taskCursor;
                    $taskEnd = $taskStart + (int)$duration - 1;
                    $dependsOn = $previousTaskCode === null ? [] : [$previousTaskCode];
                    $taskStatus = $dependsOn === [] ? 'STATUS_ACTIVE' : 'STATUS_WAITING';

                    $add(
                        $taskCode,
                        (string)$taskName,
                        sprintf('Responsavel: %s. Entrega: %s.', $taskRole, $taskName),
                        3,
                        $taskStatus,
                        0,
                        $taskStart,
                        $taskEnd,
                        $dependsOn
                    );

                    $taskCursor = $taskEnd + 1;
                    $previousTaskCode = $taskCode;
                    $lastTaskCode = $taskCode;
                }

                $epicEnd = $taskCursor - 1;
                $blueprints[$epicRow]['end_offset'] = $epicEnd;
                $blueprints[$epicRow]['duration'] = max(1, $epicEnd - $phaseStart + 1);
                $phaseEnd = max($phaseEnd, $epicEnd);

                if ($lastTaskCode !== null) {
                    $lastEpicTaskCodes[] = $lastTaskCode;
                }
            }

            $milestoneCode = sprintf('%s.M01', $phaseCode);
            $milestoneOffset = $phaseEnd + 1;
            $add(
                $milestoneCode,
                (string)$phase['milestone'],
                sprintf('Marco principal. Responsavel: %s. Criterio: entregaveis da fase validados.', $phase['role']),
                2,
                'STATUS_WAITING',
                0,
                $milestoneOffset,
                $milestoneOffset,
                $lastEpicTaskCodes,
                true
            );

            $phaseEnd = $milestoneOffset;
            $blueprints[$phaseRow]['end_offset'] = $phaseEnd;
            $blueprints[$phaseRow]['duration'] = max(1, $phaseEnd - $phaseStart + 1);
            $previousMilestone = $milestoneCode;
            $cursor = $phaseEnd + 1;
        }

        $blueprints[$rootIndex]['end_offset'] = max(0, $cursor - 1);
        $blueprints[$rootIndex]['duration'] = max(1, $cursor);

        $rowByCode = [];
        foreach ($blueprints as $index => $task) {
            $rowByCode[$task['code']] = $index + 1;
        }

        foreach ($blueprints as $index => $task) {
            $depends = [];
            foreach ($task['depends_on'] as $dependencyCode) {
                if (!isset($rowByCode[$dependencyCode])) {
                    throw new RuntimeException("Dependencia {$dependencyCode} nao encontrada para {$task['code']}.");
                }
                $depends[] = (string)$rowByCode[$dependencyCode];
            }
            $blueprints[$index]['depends'] = implode(',', $depends);
        }

        return $blueprints;
    }
}

return static function (PDO $pdo): void {
    $projectCode = 'SAAS-MT-001';
    $projectName = 'SaaS Multi-Tenant Cloud-Native';

    $columns = static function (PDO $pdo, string $table): array {
        return array_flip($pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN));
    };

    $insertRow = static function (PDO $pdo, string $table, array $data, array $availableColumns): int {
        $filtered = array_intersect_key($data, $availableColumns);
        $columnNames = array_keys($filtered);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columnNames);

        $statement = $pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columnNames),
            implode(', ', $placeholders)
        ));
        $statement->execute($filtered);

        return (int)$pdo->lastInsertId();
    };

    $updateRow = static function (
        PDO $pdo,
        string $table,
        array $data,
        array $availableColumns,
        string $where,
        array $whereParams
    ): void {
        $filtered = array_intersect_key($data, $availableColumns);
        if ($filtered === []) {
            return;
        }

        $assignments = [];
        $params = [];
        foreach ($filtered as $column => $value) {
            $placeholder = 'set_' . $column;
            $assignments[] = "{$column} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        $statement = $pdo->prepare(sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $assignments),
            $where
        ));
        $statement->execute($params + $whereParams);
    };

    $projectColumns = $columns($pdo, 'projects');
    $taskColumns = $columns($pdo, 'gantt_tasks');
    $userColumns = $columns($pdo, 'users');
    $hasProjectCompany = isset($projectColumns['company_id']);
    $hasTaskCompany = isset($taskColumns['company_id']);

    $adminId = null;
    $companyId = null;
    $activeUserWhere = isset($userColumns['deleted_at']) ? ' AND deleted_at IS NULL' : '';
    $userCompanySelect = isset($userColumns['company_id']) ? ', company_id' : '';

    foreach (['admin@techcorp.com.br', 'admin@phalcon.local'] as $email) {
        $preferredUserStatement = $pdo->prepare(
            "SELECT id{$userCompanySelect} FROM users WHERE email = :email{$activeUserWhere} LIMIT 1"
        );
        $preferredUserStatement->execute(['email' => $email]);
        $preferredUser = $preferredUserStatement->fetch(PDO::FETCH_ASSOC);

        if ($preferredUser !== false) {
            $adminId = (int)$preferredUser['id'];
            if (isset($preferredUser['company_id']) && $preferredUser['company_id'] !== null) {
                $companyId = (int)$preferredUser['company_id'];
            }
            break;
        }
    }

    if ($adminId === null) {
        $userSql = "SELECT id{$userCompanySelect} FROM users";
        $userWhere = [];

        if (isset($userColumns['deleted_at'])) {
            $userWhere[] = 'deleted_at IS NULL';
        }

        if ($userWhere !== []) {
            $userSql .= ' WHERE ' . implode(' AND ', $userWhere);
        }

        $userSql .= isset($userColumns['role'])
            ? ' ORDER BY CASE WHEN role IN ("master", "admin") THEN 0 ELSE 1 END, id ASC LIMIT 1'
            : ' ORDER BY id ASC LIMIT 1';

        $userStatement = $pdo->query($userSql);
        $user = $userStatement->fetch(PDO::FETCH_ASSOC);

        if ($user !== false) {
            $adminId = (int)$user['id'];
            if (isset($user['company_id']) && $user['company_id'] !== null) {
                $companyId = (int)$user['company_id'];
            }
        }
    }

    if (($hasProjectCompany || $hasTaskCompany) && $companyId === null) {
        $company = $pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn();
        $companyId = $company !== false ? (int)$company : null;
    }

    $projectWhere = 'code = :code';
    $projectParams = ['code' => $projectCode];
    if ($hasProjectCompany && $companyId !== null) {
        $projectWhere .= ' AND company_id = :company_id';
        $projectParams['company_id'] = $companyId;
    }

    $projectStatement = $pdo->prepare(
        "SELECT id, start_date FROM projects WHERE {$projectWhere} ORDER BY id ASC LIMIT 1"
    );
    $projectStatement->execute($projectParams);
    $project = $projectStatement->fetch(PDO::FETCH_ASSOC);

    if ($project === false && $hasProjectCompany && $companyId !== null) {
        $existingProjectStatement = $pdo->prepare(
            'SELECT id, start_date FROM projects WHERE code = :code ORDER BY id ASC LIMIT 1'
        );
        $existingProjectStatement->execute(['code' => $projectCode]);
        $existingProject = $existingProjectStatement->fetch(PDO::FETCH_ASSOC);

        if ($existingProject !== false) {
            $project = $existingProject;
            $projectIdToMove = (int)$existingProject['id'];
            $pdo->prepare('UPDATE projects SET company_id = :company_id WHERE id = :id')->execute([
                'company_id' => $companyId,
                'id' => $projectIdToMove,
            ]);

            if ($hasTaskCompany) {
                $pdo->prepare(
                    'UPDATE gantt_tasks SET company_id = :company_id WHERE project_id = :project_id'
                )->execute([
                    'company_id' => $companyId,
                    'project_id' => $projectIdToMove,
                ]);
            }
        }
    }

    $defaultStart = (new DateTimeImmutable('monday next week'))->setTime(0, 0);
    $projectStart = $project && !empty($project['start_date'])
        ? (new DateTimeImmutable((string)$project['start_date']))->setTime(0, 0)
        : $defaultStart;

    $addBusinessDays = static function (DateTimeImmutable $date, int $days): DateTimeImmutable {
        $result = $date;
        $remaining = $days;

        while ($remaining > 0) {
            $result = $result->modify('+1 day');
            if ((int)$result->format('N') <= 5) {
                $remaining--;
            }
        }

        return $result;
    };

    $blueprints = saas_multi_tenant_cloud_native_plan_blueprints();
    $rootTask = $blueprints[0];
    $projectEnd = $addBusinessDays($projectStart, (int)$rootTask['end_offset']);

    $projectData = [
        'company_id' => $companyId,
        'name' => $projectName,
        'code' => $projectCode,
        'client' => 'Portfolio profissional',
        'description' => 'Planejamento profissional de uma plataforma SaaS multi-tenant cloud-native em Python 3.13+, FastAPI, Pydantic v2, SQLAlchemy 2, Alembic, PostgreSQL, Redis, Celery, JWT/OAuth2/MFA, Pytest, Docker, Kubernetes, GitHub Actions, Prometheus, Grafana, OpenTelemetry e AWS.',
        'status' => $projectStart > new DateTimeImmutable('today') ? 'planning' : 'in_progress',
        'priority' => 'high',
        'leader_id' => $adminId,
        'start_date' => $projectStart->format('Y-m-d'),
        'deadline' => $projectEnd->format('Y-m-d'),
        'budget' => 1250000.00,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ];

    if ($project === false) {
        $projectId = $insertRow($pdo, 'projects', $projectData, $projectColumns);
    } else {
        $projectId = (int)$project['id'];
        $updateProjectData = $projectData;
        unset($updateProjectData['code'], $updateProjectData['created_by']);
        $updateRow(
            $pdo,
            'projects',
            $updateProjectData,
            $projectColumns,
            'id = :where_id',
            ['where_id' => $projectId]
        );

        if (isset($projectColumns['deleted_at'])) {
            $pdo->prepare('UPDATE projects SET deleted_at = NULL WHERE id = :id')->execute(['id' => $projectId]);
        }
    }

    if ($adminId !== null) {
        $memberStatement = $pdo->prepare(
            'INSERT INTO project_members (project_id, user_id)
             VALUES (:project_id, :user_id)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );
        $memberStatement->execute([
            'project_id' => $projectId,
            'user_id' => $adminId,
        ]);
    }

    $taskWhere = 'project_id = :project_id AND code = :code';
    if ($hasTaskCompany && $companyId !== null) {
        $taskWhere .= ' AND company_id = :company_id';
    }

    $findTask = $pdo->prepare(
        "SELECT id FROM gantt_tasks WHERE {$taskWhere} ORDER BY id ASC LIMIT 1"
    );

    foreach ($blueprints as $sortOrder => $task) {
        $startAt = $addBusinessDays($projectStart, (int)$task['start_offset'])->setTime(0, 0);
        $endAt = $addBusinessDays($projectStart, (int)$task['end_offset'])->setTime(23, 59, 59);

        $taskData = [
            'project_id' => $projectId,
            'company_id' => $companyId,
            'code' => $task['code'],
            'name' => $task['name'],
            'description' => $task['description'] . ' WBS: ' . $task['code'] . '.',
            'level' => $task['level'],
            'status' => $task['status'],
            'progress' => $task['progress'],
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'duration' => $task['duration'],
            'depends' => $task['depends'],
            'sort_order' => $sortOrder,
            'collapsed' => 0,
            'start_is_milestone' => $task['milestone'] ? 1 : 0,
            'end_is_milestone' => $task['milestone'] ? 1 : 0,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ];

        $findParams = [
            'project_id' => $projectId,
            'code' => $task['code'],
        ];
        if ($hasTaskCompany && $companyId !== null) {
            $findParams['company_id'] = $companyId;
        }

        $findTask->execute($findParams);
        $taskId = $findTask->fetchColumn();

        if ($taskId === false) {
            $insertRow($pdo, 'gantt_tasks', $taskData, $taskColumns);
            continue;
        }

        unset($taskData['project_id'], $taskData['code'], $taskData['created_by']);
        $updateRow(
            $pdo,
            'gantt_tasks',
            $taskData,
            $taskColumns,
            'id = :where_id',
            ['where_id' => (int)$taskId]
        );
    }
};
