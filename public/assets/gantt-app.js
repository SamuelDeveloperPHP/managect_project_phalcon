(function () {
  var csrfToken = document.querySelector('meta[name="csrf-token"]');
  var projectId = document.body.getAttribute('data-project-id');
  var toastEl = document.getElementById('gantt-toast');
  var toastTimer = null;

  function setStatus(message, type) {
    if (!toastEl) return;

    toastEl.textContent = message;
    toastEl.className = type || '';
    toastEl.style.display = 'block';

    // Auto-hide after 5s for success/info, stay for error
    if (toastTimer) clearTimeout(toastTimer);
    if (type !== 'error') {
      toastTimer = setTimeout(function () {
        toastEl.style.display = 'none';
      }, 5000);
    }
  }

  function notify(title, message, icon) {
    if (window.Swal) {
      Swal.fire(title, message, icon);
      return;
    }
    alert(title + ': ' + message);
  }

  function currentFullscreenElement() {
    return document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.msFullscreenElement ||
      null;
  }

  function requestFullscreen(element) {
    var request = element.requestFullscreen ||
      element.webkitRequestFullscreen ||
      element.msRequestFullscreen;

    return request ? request.call(element) : null;
  }

  function exitFullscreen() {
    var exit = document.exitFullscreen ||
      document.webkitExitFullscreen ||
      document.msExitFullscreen;

    return exit ? exit.call(document) : null;
  }

  function updateFullscreenButton(active) {
    var button = $('#fullscrbtn');

    if (!button.length) {
      return;
    }

    if (!button.find('.gantt-fullscreen-icon').length) {
      button.empty().append($('<span>', {
        'class': 'gantt-fullscreen-icon',
        'aria-hidden': 'true'
      }));
    }

    button
      .toggleClass('is-fullscreen', active)
      .attr('title', active ? 'Sair da tela cheia' : 'Tela cheia')
      .attr('aria-label', active ? 'Sair da tela cheia' : 'Tela cheia')
      .attr('aria-pressed', active ? 'true' : 'false');
  }

  function resizeGanttAfterFullscreen() {
    if (!window.ge || typeof window.ge.resize !== 'function') {
      return;
    }

    setTimeout(function () {
      window.ge.resize();

      if (window.ge.gantt && typeof window.ge.gantt.redraw === 'function') {
        window.ge.gantt.redraw();
      }
    }, 80);
  }

  function patchFullscreenButton() {
    if (!window.GanttMaster || window.GanttMaster.prototype.projectFullscreenPatched) {
      return;
    }

    window.GanttMaster.prototype.projectFullscreenPatched = true;
    window.GanttMaster.prototype.fullScreen = function () {
      var workspace = this.workSpace;
      var workspaceElement = workspace && workspace[0];
      var nativeFullscreenElement = currentFullscreenElement();
      var isActive = workspace && workspace.is('.ganttFullScreen');
      var nativeIsActive = nativeFullscreenElement === workspaceElement;
      var fullscreenChange;

      if (!workspace || !workspaceElement) {
        return;
      }

      if (isActive || nativeIsActive) {
        workspace.removeClass('ganttFullScreen').resize();
        updateFullscreenButton(false);

        if (nativeFullscreenElement) {
          fullscreenChange = exitFullscreen();
        }
      } else {
        workspace.addClass('ganttFullScreen').resize();
        updateFullscreenButton(true);
        fullscreenChange = requestFullscreen(workspaceElement);
      }

      if (fullscreenChange && typeof fullscreenChange.catch === 'function') {
        fullscreenChange.catch(function () {
          updateFullscreenButton(workspace.is('.ganttFullScreen'));
          resizeGanttAfterFullscreen();
        });
      }

      resizeGanttAfterFullscreen();
    };

    $(document).on('fullscreenchange webkitfullscreenchange msfullscreenchange', function () {
      if (!window.ge || !window.ge.workSpace) {
        return;
      }

      var active = currentFullscreenElement() === window.ge.workSpace[0];
      window.ge.workSpace.toggleClass('ganttFullScreen', active).resize();
      updateFullscreenButton(active);
      resizeGanttAfterFullscreen();
    });

    $(document).on('keydown', function (event) {
      if (event.key !== 'Escape' || currentFullscreenElement() || !window.ge || !window.ge.workSpace) {
        return;
      }

      if (window.ge.workSpace.is('.ganttFullScreen')) {
        window.ge.workSpace.removeClass('ganttFullScreen').resize();
        updateFullscreenButton(false);
        resizeGanttAfterFullscreen();
      }
    });
  }

  function projectRequest(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken ? csrfToken.content : ''
    }, options.headers || {});

    return fetch(url, options).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || data.success === false) {
          throw new Error(data.message || 'Nao foi possivel processar o cronograma.');
        }
        return data;
      });
    });
  }

  function loadGantt() {
    setStatus('Carregando cronograma...', 'info');
    patchFullscreenButton();

    projectRequest('/api/projects/' + projectId + '/gantt')
      .then(function (data) {
        window.ge = new GanttMaster();
        tuneLargeTimeline(data.project);
        window.ge.set100OnClose = true;
        window.ge.shrinkParent = true;
        window.ge.resourceUrl = '/assets/jquery-gantt/res/';
        window.ge.init($('#workSpace'));
        updateFullscreenButton(false);

        if (typeof window.loadI18n === 'function') {
          window.loadI18n();
        }

        window.ge.loadProject(data.project);
        resetGanttScroll();
        window.ge.gantt.zoom = '1M';
        window.ge.gantt.gridChanged = true;
        window.ge.gantt.redraw();
        fitInitialSplitter();
        window.ge.checkpoint();
        setStatus('Cronograma carregado. Edite as linhas e clique em salvar.', 'success');
      })
      .catch(function (error) {
        setStatus(error.message, 'error');
        notify('Erro', error.message, 'error');
      });
  }

  function fitInitialSplitter() {
    if (!window.ge || !window.ge.splitter || !window.ge.element) {
      return;
    }

    var areaWidth = window.ge.element.width();
    if (!areaWidth) {
      return;
    }

    var tableWidth = window.ge.editor && window.ge.editor.gridified
      ? window.ge.editor.gridified.find('.gdfTable').first().outerWidth()
      : 0;
    var targetWidth = Math.max(tableWidth + 12, Math.min(760, areaWidth * 0.6));
    var targetPercent = Math.max(42, Math.min(60, (targetWidth / areaWidth) * 100));
    window.ge.splitter.resize(targetPercent);
  }

  window.saveGanttOnServer = function () {
    if (!window.ge) {
      notify('Aguarde', 'O cronograma ainda esta carregando.', 'info');
      return false;
    }

    setStatus('Salvando cronograma...', 'info');

    var project = window.ge.saveProject();

    projectRequest('/api/projects/' + projectId + '/gantt', {
      method: 'POST',
      body: JSON.stringify(project)
    })
      .then(function (data) {
        tuneLargeTimeline(data.project);
        window.ge.loadProject(data.project);
        resetGanttScroll();
        window.ge.gantt.redraw();
        window.ge.checkpoint();
        setStatus(data.message || 'Cronograma salvo.', 'success');
        notify('Sucesso', data.message || 'Cronograma salvo.', 'success');
      })
      .catch(function (error) {
        setStatus(error.message, 'error');
        notify('Erro', error.message, 'error');
      });

    return false;
  };

  function tuneLargeTimeline(project) {
    if (!window.ge || !project || !Array.isArray(project.tasks)) {
      return;
    }

    if (project.tasks.length > 80) {
      window.ge.rowBufferSize = project.tasks.length + 10;
    }
  }

  function resetGanttScroll() {
    if (!window.ge || !window.ge.splitter) {
      return;
    }

    if (window.ge.splitter.firstBox) {
      window.ge.splitter.firstBox.scrollTop(0);
    }

    if (window.ge.splitter.secondBox) {
      window.ge.splitter.secondBox.scrollTop(0);
    }

    window.ge.firstScreenLine = 0;
  }

  window.newProject = function () {
    if (!window.ge) {
      return false;
    }
    window.ge.reset();
    setStatus('Cronograma limpo. Clique em salvar para gravar no banco.', 'info');
    return false;
  };

  window.editResources = function () {
    if (window.ge && window.ge.editor && typeof window.ge.editor.openResourceEditor === 'function') {
      window.ge.editor.openResourceEditor();
    }
    return false;
  };

  window.uploadProject = function (file) {
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      try {
        var project = JSON.parse(e.target.result);
        if (window.ge && project) {
          window.ge.loadProject(project);
          window.ge.checkpoint();
          setStatus('Cronograma importado com sucesso. Clique em "Salvar cronograma" para gravar no banco.', 'info');
        }
      } catch (err) {
        notify('Erro ao importar', 'O arquivo JSON selecionado e invalido.', 'error');
      }
    };
    reader.readAsText(file);
  };

  window.upload = function (file) {
    window.uploadProject(file);
  };

  window.jsonErrorHandling = function (response) {
    notify('Erro', response && response.message ? response.message : 'Erro no Gantt.', 'error');
  };

  // Use 'pagehide' + 'beforeunload' correctly:
  // Chrome 115+ blocks unload listeners when Permissions-Policy: unload=() is set.
  // We use only e.preventDefault() (modern standard) without returnValue assignment.
  window.addEventListener('beforeunload', function (e) {
    if (window.ge && typeof window.ge.canUndo === 'function' && window.ge.canUndo()) {
      e.preventDefault();
      // Note: returnValue is deprecated in modern browsers - e.preventDefault() is enough
    }
  });

  $(loadGantt);
})();
