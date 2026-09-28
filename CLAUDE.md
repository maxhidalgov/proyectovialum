# CLAUDE.md

Guía para agentes trabajando en este repositorio.

## Flujo de ideas (IDEAS.md)

### Anotar sin implementar
- Si el usuario escribe "idea:", "anota:" o "para después:", agrega la idea a la sección
  **Pendientes** de IDEAS.md con el formato:
  `- [ ] AAAA-MM-DD — descripción breve (contexto si lo dio)`
- NO implementes nada al anotar. Confirma en una línea y espera la siguiente instrucción.
- Si el usuario dicta varias ideas seguidas, anótalas todas antes de hacer otra cosa.

### Trabajar una idea
- Solo implementa una idea cuando el usuario lo pida explícitamente.
- Al empezar, muévela a **En curso**.
- Antes de empezar cualquier tarea, revisa IDEAS.md y avisa si hay ideas pendientes
  relacionadas (no las hagas sin preguntar).
- Si descubres algo que habría que hacer pero no es parte de la tarea, anótalo en
  Pendientes en vez de hacerlo.

### Al terminar
- Mueve la idea a **Hechas**, marcada [x], con fecha de término.
- Agrega una entrada al inicio de docs/LOG.md con el formato indicado en ese archivo.
- Incluye los cambios de IDEAS.md y docs/LOG.md en el mismo commit que el trabajo.
