(function (window) {
  "use strict";

  Date.monthNames = [
    "janeiro", "fevereiro", "março", "abril", "maio", "junho",
    "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"
  ];
  Date.monthAbbreviations = [
    "jan", "fev", "mar", "abr", "mai", "jun",
    "jul", "ago", "set", "out", "nov", "dez"
  ];
  Date.dayNames = [
    "domingo", "segunda-feira", "terça-feira", "quarta-feira",
    "quinta-feira", "sexta-feira", "sábado"
  ];
  Date.dayAbbreviations = ["dom", "seg", "ter", "qua", "qui", "sex", "sáb"];
  Date.preferAmericanFormat = false;
  Date.firstDayOfWeek = 1;
  Date.defaultFormat = "dd/MM/yyyy";
  Date.masks = {
    fullDate: "EEEE, dd/MM/yyyy",
    shortTime: "HH:mm"
  };
  Date.today = "Hoje";

  Number.decimalSeparator = ",";
  Number.groupingSeparator = ".";
  Number.minusSign = "-";
  Number.currencyFormat = "###.##0,00";

  window.loadI18n = function () {
    GanttMaster.messages = {
      "CANNOT_WRITE": "Sem permissão para alterar a seguinte tarefa:",
      "CHANGE_OUT_OF_SCOPE": "Não é possível atualizar o projeto sem permissão para alterar o projeto pai.",
      "START_IS_MILESTONE": "A data de início é um marco.",
      "END_IS_MILESTONE": "A data de término é um marco.",
      "TASK_HAS_CONSTRAINTS": "A tarefa possui restrições.",
      "GANTT_ERROR_DEPENDS_ON_OPEN_TASK": "Erro: existe dependência de uma tarefa aberta.",
      "GANTT_ERROR_DESCENDANT_OF_CLOSED_TASK": "Erro: existe uma tarefa descendente de uma tarefa encerrada.",
      "TASK_HAS_EXTERNAL_DEPS": "Esta tarefa possui dependências externas.",
      "GANNT_ERROR_LOADING_DATA_TASK_REMOVED": "Ocorreu um erro ao carregar os dados. Uma tarefa foi removida.",
      "GANTT_ERROR_LOADING_DATA_TASK_REMOVED": "Ocorreu um erro ao carregar os dados. Uma tarefa foi removida.",
      "CIRCULAR_REFERENCE": "Referência circular.",
      "CANNOT_DEPENDS_ON_ANCESTORS": "A tarefa não pode depender de tarefas ancestrais.",
      "CANNOT_DEPENDS_ON_DESCENDANTS": "A tarefa não pode depender de tarefas descendentes.",
      "CANNOT_CREATE_SAME_LINK": "Não é possível criar a mesma dependência novamente.",
      "INVALID_DATE_FORMAT": "A data informada não está no formato válido.",
      "CANNOT_CLOSE_TASK_IF_OPEN_ISSUE": "Não é possível concluir uma tarefa com pendências abertas.",
      "TASK_MOVE_INCONSISTENT_LEVEL": "Não é possível trocar tarefas de níveis diferentes.",
      "CANNOT_MOVE_TASK": "Não é possível mover a tarefa.",
      "PLEASE_SAVE_PROJECT": "Salve o projeto antes de continuar.",
      "GANTT_SEMESTER": "Semestre",
      "GANTT_SEMESTER_SHORT": "sem.",
      "GANTT_QUARTER": "Trimestre",
      "GANTT_QUARTER_SHORT": "trim.",
      "GANTT_WEEK": "Semana",
      "GANTT_WEEK_SHORT": "sem."
    };
  };

  window.GanttLocale = "pt-BR";
})(window);
