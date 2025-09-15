# Documentação Detalhada - SM Student Control

## Visão Geral do Plugin

O **SM Student Control** é um plugin para WordPress desenvolvido para gerenciar e monitorar as atividades dos alunos no Masterstudy LMS. Este plugin oferece aos administradores uma visão abrangente do engajamento dos estudantes, incluindo cursos matriculados, lições concluídas, questionários realizados, pontuações dos quizzes e certificados recebidos.

## Arquitetura do Plugin

### Estrutura de Arquivos

```
sm-student-control/
├── index.php                           # Arquivo de entrada principal
├── sm-student-control.php             # Arquivo principal do plugin
├── README.md                          # Documentação em português
├── admin/                             # Funcionalidades administrativas
│   ├── class-sm-student-control-admin.php
│   ├── class-student-details.php
│   └── views/
│       ├── admin-page.php
│       ├── student-details.php
│       ├── student-gamipress.php
│       ├── students-filter-form.php
│       └── students-list-table.php
├── assets/                            # Recursos estáticos
│   ├── css/
│   │   └── sm-student-control-admin.css
│   └── js/
│       ├── main.js
│       ├── sm-student-control-admin.js
│       └── sm-student-control-cache.js
└── includes/                          # Classes principais
    ├── class-sm-student-control-cache.php
    ├── class-sm-student-control-data.php
    └── class-sm-student-control-loader.php
```

### Componentes Principais

#### 1. SM_Student_Control_Loader
- **Arquivo**: `includes/class-sm-student-control-loader.php`
- **Responsabilidade**: Inicialização e carregamento de todas as classes do plugin
- **Funcionalidades**:
  - Carrega dependências necessárias
  - Instancia classes de administração quando apropriado
  - Gerencia o ciclo de vida do plugin

#### 2. SM_Student_Control_Admin
- **Arquivo**: `admin/class-sm-student-control-admin.php`
- **Responsabilidade**: Interface administrativa do plugin
- **Funcionalidades**:
  - Adiciona menu no painel WordPress
  - Carrega estilos e scripts
  - Gerencia páginas administrativas
  - Trata requisições AJAX

#### 3. SM_Student_Control_Data
- **Arquivo**: `includes/class-sm-student-control-data.php`
- **Responsabilidade**: Acesso e processamento de dados do LMS
- **Funcionalidades**: (detalhado na próxima seção)

#### 4. SM_Student_Control_Cache
- **Arquivo**: `includes/class-sm-student-control-cache.php`
- **Responsabilidade**: Sistema de cache para otimização de performance
- **Funcionalidades**: (detalhado na próxima seção)

## Integração com Masterstudy LMS

### Como Funciona a Obtenção de Dados

O plugin integra-se com o Masterstudy LMS através de consultas diretas ao banco de dados WordPress. Todas as informações são extraídas das seguintes tabelas principais:

#### Tabelas do Masterstudy LMS Utilizadas

1. **`wp_stm_lms_user_courses`** - Matrículas dos alunos em cursos
2. **`wp_stm_lms_user_lessons`** - Progresso das lições
3. **`wp_stm_lms_user_quizzes`** - Resultados dos questionários
4. **`wp_posts`** - Informações dos cursos e lições
5. **`wp_users`** - Dados básicos dos usuários
6. **`wp_usermeta`** - Metadados dos usuários (último acesso, etc.)

### Métodos Principais de Obtenção de Dados

#### 1. `get_all_students()`
```php
public static function get_all_students($search = '', $course_id = '', $last_access_month = '')
```
**Funcionalidade**: Recupera todos os alunos com filtros opcionais
**Processo**:
1. Consulta usuários que possuem matrículas em cursos OU têm papel de "subscriber"
2. Aplica filtros de busca por nome/email
3. Filtra por curso específico (se informado)
4. Filtra por mês de último acesso
5. Retorna dados formatados com informações básicas

#### 2. `get_student_data()`
```php
public static function get_student_data($user_id)
```
**Funcionalidade**: Obtém dados detalhados de um aluno específico
**Processo**:
1. Recupera informações básicas do usuário (`wp_users`)
2. Processa nome completo (first_name + last_name)
3. Calcula último acesso através de múltiplas fontes
4. Formata datas com timezone correto (UTC-3)

#### 3. `get_student_last_access()`
```php
public static function get_student_last_access($user_id)
```
**Funcionalidade**: Determina o último acesso do aluno
**Fontes verificadas** (em ordem de prioridade):
1. Último quiz realizado (`wp_stm_lms_user_quizzes.end_time`)
2. Última lição visualizada (`wp_stm_lms_user_lessons.end_time`)
3. Metadado `last_login` do usuário
4. Data de atualização da matrícula (`wp_stm_lms_user_courses.time_updated`)

#### 4. `get_student_courses()`
```php
public static function get_student_courses($user_id)
```
**Funcionalidade**: Lista cursos matriculados do aluno
**Processo**:
1. Consulta `wp_stm_lms_user_courses` para o usuário
2. Junta com `wp_posts` para obter títulos dos cursos
3. Calcula progresso percentual
4. Determina status do curso
5. Formata datas de matrícula

#### 5. `get_student_recent_quizzes()`
```php
public static function get_student_recent_quizzes($user_id, $limit = 10)
```
**Funcionalidade**: Últimos 10 quizzes realizados
**Processo**:
1. Consulta `wp_stm_lms_user_quizzes` com JOIN em `wp_posts`
2. Ordena por data de conclusão (mais recentes primeiro)
3. Limita a 10 resultados
4. Formata timestamps para timezone local

#### 6. `get_student_recent_lessons()`
```php
public static function get_student_recent_lessons($user_id, $limit = 10)
```
**Funcionalidade**: Últimas 10 lições visualizadas
**Processo**:
1. Consulta `wp_stm_lms_user_lessons` com JOIN em `wp_posts`
2. Ordena por data de conclusão
3. Gera URLs das lições quando possível
4. Formata datas com timezone correto

### Tratamento de Datas e Timezones

O plugin implementa um sistema robusto de tratamento de datas:

#### Função `format_date_safely()`
```php
public static function format_date_safely($date, $format = '')
```
**Características**:
- Converte qualquer formato de data para timestamp UNIX
- Ajusta automaticamente para timezone do WordPress (UTC-3)
- Usa `DateTime` para precisão máxima
- Fallback para `date_i18n()` em caso de erro

#### Função `to_timestamp()`
```php
public static function to_timestamp($date_value)
```
**Conversões suportadas**:
- Timestamps UNIX existentes
- Strings MySQL (`YYYY-MM-DD HH:MM:SS`)
- Outros formatos de string
- Valores inválidos retornam 0

## Sistema de Cache

### Arquitetura do Cache

O plugin utiliza uma tabela dedicada no banco de dados para cache:

```sql
CREATE TABLE wp_sm_student_control_cache (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    full_name varchar(255) DEFAULT '',
    email varchar(255) DEFAULT '',
    username varchar(255) DEFAULT '',
    registration_date varchar(255) DEFAULT '',
    last_access_timestamp bigint(20) DEFAULT 0,
    courses_data longtext DEFAULT NULL,
    course_history_data longtext DEFAULT NULL,
    quizzes_data longtext DEFAULT NULL,
    lessons_data longtext DEFAULT NULL,
    all_lessons_count int(11) DEFAULT 0,
    all_quizzes_count int(11) DEFAULT 0,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id)
)
```

### Funcionalidades do Cache

#### 1. `update_cache()`
```php
public static function update_cache($user_id)
```
**Processo de atualização**:
1. Verifica se usuário existe
2. Coleta dados básicos do usuário
3. Calcula último acesso
4. Obtém cursos ativos
5. Gera histórico de cursos (cursos não mais ativos)
6. Coleta últimos 10 quizzes
7. Coleta últimas 10 lições
8. Conta total de lições e quizzes
9. Armazena tudo em JSON na tabela de cache

#### 2. `get_all_students_from_cache()`
```php
public static function get_all_students_from_cache($search, $course_id, $last_access_month, $orderby, $order, $per_page, $paged)
```
**Funcionalidades**:
- Busca com filtros (nome/email, curso, mês de acesso)
- Ordenação por múltiplas colunas
- Paginação eficiente
- Retorna array com items e total

#### 3. `get_student_from_cache()`
```php
public static function get_student_from_cache($student_id)
```
**Retorna dados completos**:
- Informações básicas do usuário
- Cursos matriculados
- Histórico de cursos
- Últimos quizzes
- Últimas lições
- Contadores totais

### Atualização Automática

#### Cron Job Diário
```php
add_action('sm_student_control_daily_cache_update', ['SM_Student_Control_Cache', 'daily_cache_handler']);
```
- Executa diariamente à meia-noite
- Processa alunos em lotes de 50
- Pausa de 2 segundos entre lotes para não sobrecarregar

#### Atualização Manual
- Botão "Atualizar Dados dos Alunos" na interface
- Processa em lotes via AJAX
- Mostra progresso em tempo real

## Interface Administrativa

### Páginas Principais

#### 1. Lista de Alunos (`students-list-table.php`)
**Funcionalidades**:
- Tabela paginada com alunos
- Filtros por nome/email, curso, mês de último acesso
- Ordenação por nome, data de registro, último acesso
- Links para detalhes individuais
- Botão de atualização em lote

#### 2. Detalhes do Aluno (`student-details.php`)
**Seções**:
- **Perfil**: Nome, email, username, datas
- **Cursos Matriculados**: Tabela com progresso e status
- **Últimos Quizzes**: Tabela com pontuações
- **Últimas Lições**: Tabela com datas de conclusão
- **Ações**: Atualizar cache, editar usuário

### Funcionalidades JavaScript

#### 1. `sm-student-control-admin.js`
- Inicializa DataTables (se disponível)
- Trata cliques em botões de atualização
- AJAX para atualização individual de cache

#### 2. `sm-student-control-cache.js`
- Sistema de atualização em lote
- Progresso visual durante atualização
- Recarregamento automático após conclusão

### Estilos CSS

#### Responsividade
- Layout flexível com `flex-wrap`
- Grid responsivo para informações do aluno
- Media queries para dispositivos móveis

#### Componentes
- Cards com sombra para seções
- Botões padronizados do WordPress
- Tabelas com estilo WordPress nativo

## Funcionalidades Avançadas

### Filtros e Busca

#### Filtros Disponíveis
1. **Busca por nome/email**: Campo de texto livre
2. **Filtro por curso**: Dropdown com todos os cursos
3. **Filtro por mês de acesso**: Input tipo `month`

#### Ordenação
- Nome (asc/desc)
- Data de registro (asc/desc)
- Último acesso (asc/desc)

### Tratamento de Erros

#### Validações Implementadas
- Verificação de existência de usuário
- Tratamento de dados corrompidos
- Fallbacks para campos vazios
- Logs de erro para debugging

#### Timeouts e Limites
- Limite de 120 segundos para operações pesadas
- Processamento em lotes para evitar sobrecarga
- Memory limit aumentado para 256MB em operações críticas

## Considerações Técnicas

### Performance
- **Cache**: Reduz consultas ao banco de ~80%
- **Paginação**: Limita resultados a 30 alunos por página
- **Índices**: UNIQUE KEY em user_id para buscas rápidas
- **JSON Storage**: Dados complexos armazenados em JSON

### Segurança
- **Nonces**: Proteção contra CSRF em todas as requisições AJAX
- **Sanitização**: Todos os inputs são sanitizados
- **Permissões**: Verificação de `manage_options` para ações administrativas
- **Prepared Statements**: Todas as consultas SQL usam prepared statements

### Compatibilidade
- **WordPress**: 5.0+
- **Masterstudy LMS**: Qualquer versão que use as tabelas padrão
- **PHP**: 7.0+ (recomendado 7.4+)
- **MySQL**: 5.6+

## Fluxo de Dados

### Do LMS para a Interface

1. **Fonte**: Tabelas do Masterstudy LMS
2. **Processamento**: Classe `SM_Student_Control_Data`
3. **Cache**: Armazenamento em `wp_sm_student_control_cache`
4. **Interface**: Templates PHP com dados do cache
5. **Interação**: JavaScript para funcionalidades dinâmicas

### Atualização de Dados

1. **Trigger**: Cron diário ou ação manual
2. **Processamento**: Coleta de dados frescos do LMS
3. **Armazenamento**: Update na tabela de cache
4. **Interface**: Recarregamento automático da página

## Conclusão

O SM Student Control é um plugin bem arquiteturado que oferece uma solução completa para monitoramento de alunos no Masterstudy LMS. Sua arquitetura em camadas, sistema de cache eficiente e interface intuitiva fazem dele uma ferramenta poderosa para administradores de plataformas de ensino online.

A integração profunda com o LMS permite acesso a dados detalhados de engajamento dos alunos, enquanto o sistema de cache garante performance adequada mesmo com grandes volumes de dados.