(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);throw new Error("Cannot find module '"+o+"'")}var f=n[o]={exports:{}};t[o][0].call(f.exports,function(e){var n=t[o][1][e];return s(n?n:e)},f,f.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
/* global WPFormsUtils */
'use strict';

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
(function ($) {
  /**
   * All connections are slow by default.
   *
   * @since 1.6.2
   *
   * @type {boolean|null}
   */
  var isSlow = null;

  /**
   * Previously submitted data.
   *
   * @since 1.7.1
   *
   * @type {Array}
   */
  var submittedValues = [];

  /**
   * Default settings for our speed test.
   *
   * @since 1.6.2
   *
   * @type {{maxTime: number, payloadSize: number}}
   */
  var speedTestSettings = {
    maxTime: 3000,
    // Max time (ms) it should take to be considered a 'fast connection'.
    payloadSize: 100 * 1024 // Payload size.
  };

  /**
   * Create a random payload for the speed test.
   *
   * @since 1.6.2
   *
   * @returns {string} Random payload.
   */
  function getPayload() {
    var data = '';
    for (var i = 0; i < speedTestSettings.payloadSize; ++i) {
      data += String.fromCharCode(Math.round(Math.random() * 36 + 64));
    }
    return data;
  }

  /**
   * Run speed tests and flag the clients as slow or not. If a connection
   * is slow it would let the backend know and the backend most likely
   * would disable parallel uploads and would set smaller chunk sizes.
   *
   * @since 1.6.2
   *
   * @param {Function} next Function to call when the speed detection is done.
   */
  function speedTest(next) {
    if (null !== isSlow) {
      setTimeout(next);
      return;
    }
    var data = getPayload();
    var start = new Date();
    wp.ajax.post({
      action: 'wpforms_file_upload_speed_test',
      data: data
    }).then(function () {
      var delta = new Date() - start;
      isSlow = delta >= speedTestSettings.maxTime;
      next();
    }).fail(function () {
      isSlow = true;
      next();
    });
  }

  /**
   * Toggle loading message above submit button.
   *
   * @since 1.5.6
   *
   * @param {object} $form jQuery form element.
   *
   * @returns {Function} event handler function.
   */
  function toggleLoadingMessage($form) {
    return function () {
      if ($form.find('.wpforms-uploading-in-progress-alert').length) {
        return;
      }
      $form.find('.wpforms-submit-container').before("<div class=\"wpforms-error-alert wpforms-uploading-in-progress-alert\">\n\t\t\t\t\t\t".concat(window.wpforms_file_upload.loading_message, "\n\t\t\t\t\t</div>"));
    };
  }

  /**
   * Is a field loading?
   *
   * @since 1.7.6
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {boolean} true if the field is loading.
   */
  function uploadInProgress(dz) {
    return dz.loading > 0 || dz.getFilesWithStatus('error').length > 0;
  }

  /**
   * Is at least one field loading?
   *
   * @since 1.7.6
   *
   * @returns {boolean} true if at least one field is loading.
   */
  function anyUploadsInProgress() {
    var anyUploadsInProgress = false;
    window.wpforms.dropzones.some(function (dz) {
      if (uploadInProgress(dz)) {
        anyUploadsInProgress = true;
        return true;
      }
    });
    return anyUploadsInProgress;
  }

  /**
   * Disable submit button and add overlay.
   *
   * @param {object} $form jQuery form element.
   */
  function disableSubmitButton($form) {
    // Find the primary submit button and the "Next" button for multi-page forms.
    var $btn = $form.find('.wpforms-submit');
    var $btnNext = $form.find('.wpforms-page-next:visible');
    var handler = toggleLoadingMessage($form); // Get the handler function for loading message toggle.

    // For multi-pages layout, use the "Next" button instead of the primary submit button.
    if ($form.find('.wpforms-page-indicator').length !== 0 && $btnNext.length !== 0) {
      $btn = $btnNext;
    }

    // Disable the submit button.
    $btn.prop('disabled', true);
    WPFormsUtils.triggerEvent($form, 'wpformsFormSubmitButtonDisable', [$form, $btn]);

    // If the overlay is not already added and the button is of type "submit", add an overlay.
    if (!$form.find('.wpforms-submit-overlay').length && $btn.attr('type') === 'submit') {
      // Add a container for the overlay and append the overlay element to it.
      $btn.parent().addClass('wpforms-submit-overlay-container');
      $btn.parent().append('<div class="wpforms-submit-overlay"></div>');

      // Set the overlay dimensions to match the submit button's size.
      $form.find('.wpforms-submit-overlay').css({
        width: "".concat($btn.outerWidth(), "px"),
        height: "".concat($btn.parent().outerHeight(), "px")
      });

      // Attach the click event to the overlay so that it triggers the handler function.
      $form.find('.wpforms-submit-overlay').on('click', handler);
    }
  }

  /**
   * Disable submit button when we are sending files to the server.
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   */
  function toggleSubmit(dz) {
    // eslint-disable-line complexity

    var $form = jQuery(dz.element).closest('form'),
      $btn = $form.find('.wpforms-submit'),
      $btnNext = $form.find('.wpforms-page-next:visible'),
      handler = toggleLoadingMessage($form),
      disabled = uploadInProgress(dz);

    // For multi-pages layout.
    if ($form.find('.wpforms-page-indicator').length !== 0 && $btnNext.length !== 0) {
      $btn = $btnNext;
    }
    var isButtonDisabled = Boolean($btn.prop('disabled')) || $btn.hasClass('wpforms-disabled');
    if (disabled === isButtonDisabled) {
      return;
    }
    if (disabled) {
      disableSubmitButton($form);
      return;
    }
    if (anyUploadsInProgress()) {
      return;
    }
    $btn.prop('disabled', false);
    WPFormsUtils.triggerEvent($form, 'wpformsFormSubmitButtonRestore', [$form, $btn]);
    $form.find('.wpforms-submit-overlay').off('click', handler);
    $form.find('.wpforms-submit-overlay').remove();
    $btn.parent().removeClass('wpforms-submit-overlay-container');
    if ($form.find('.wpforms-uploading-in-progress-alert').length) {
      $form.find('.wpforms-uploading-in-progress-alert').remove();
    }
  }

  /**
   * Try to parse JSON or return false.
   *
   * @since 1.5.6
   *
   * @param {string} str JSON string candidate.
   *
   * @returns {*} Parse object or false.
   */
  function parseJSON(str) {
    try {
      return JSON.parse(str);
    } catch (e) {
      return false;
    }
  }

  /**
   * Leave only objects with length.
   *
   * @since 1.5.6
   *
   * @param {object} el Any array.
   *
   * @returns {bool} Has length more than 0 or no.
   */
  function onlyWithLength(el) {
    return el.length > 0;
  }

  /**
   * Leave only positive elements.
   *
   * @since 1.5.6
   *
   * @param {*} el Any element.
   *
   * @returns {*} Filter only positive.
   */
  function onlyPositive(el) {
    return el;
  }

  /**
   * Get xhr.
   *
   * @since 1.5.6
   *
   * @param {object} el Object with xhr property.
   *
   * @returns {*} Get XHR.
   */
  function getXHR(el) {
    return el.chunkResponse || el.xhr;
  }

  /**
   * Get response text.
   *
   * @since 1.5.6
   *
   * @param {object} el Xhr object.
   *
   * @returns {object} Response text.
   */
  function getResponseText(el) {
    return typeof el === 'string' ? el : el.responseText;
  }

  /**
   * Get data.
   *
   * @since 1.5.6
   *
   * @param {object} el Object with data property.
   *
   * @returns {object} Data.
   */
  function getData(el) {
    return el.data;
  }

  /**
   * Get value from files.
   *
   * @since 1.5.6
   *
   * @param {object} files Dropzone files.
   *
   * @returns {object} Prepared value.
   */
  function getValue(files) {
    return files.map(getXHR).filter(onlyPositive).map(getResponseText).filter(onlyWithLength).map(parseJSON).filter(onlyPositive).map(getData);
  }

  /**
   * Sending event higher order function.
   *
   * @since 1.5.6
   * @since 1.5.6.1 Added special processing of a file that is larger than server's post_max_size.
   *
   * @param {object} dz Dropzone object.
   * @param {object} data Adding data to request.
   *
   * @returns {Function} Handler function.
   */
  function sending(dz, data) {
    return function (file, xhr, formData) {
      /*
       * We should not allow sending a file, that exceeds server post_max_size.
       * With this "hack" we redefine the default send functionality
       * to prevent only this object from sending a request at all.
       * The file that generated that error should be marked as rejected,
       * so Dropzone will silently ignore it.
       *
       * If Chunks are enabled the file size will never exceed (by a PHP constraint) the
       * postMaxSize. This block shouldn't be removed nonetheless until the "modern" upload is completely
       * deprecated and removed.
       */
      if (file.size > this.dataTransfer.postMaxSize) {
        xhr.send = function () {};
        file.accepted = false;
        file.processing = false;
        file.status = 'rejected';
        file.previewElement.classList.add('dz-error');
        file.previewElement.classList.add('dz-complete');
        return;
      }
      Object.keys(data).forEach(function (key) {
        formData.append(key, data[key]);
      });
    };
  }

  /**
   * Convert files to input value.
   *
   * @since 1.5.6
   * @since 1.7.1 Added the dz argument.
   *
   * @param {object} files Files list.
   * @param {object} dz Dropzone object.
   *
   * @returns {string} Converted value.
   */
  function convertFilesToValue(files, dz) {
    if (!submittedValues[dz.dataTransfer.formId] || !submittedValues[dz.dataTransfer.formId][dz.dataTransfer.fieldId]) {
      return files.length ? JSON.stringify(files) : '';
    }
    files.push.apply(files, submittedValues[dz.dataTransfer.formId][dz.dataTransfer.fieldId]);
    return JSON.stringify(files);
  }

  /**
   * Get input element.
   *
   * @since 1.7.1
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {jQuery} Hidden input element.
   */
  function getInput(dz) {
    return jQuery(dz.element).parents('.wpforms-field-file-upload').find('input[name=' + dz.dataTransfer.name + ']');
  }

  /**
   * Update value in input.
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   */
  function updateInputValue(dz) {
    var $input = getInput(dz);
    $input.val(convertFilesToValue(getValue(dz.files), dz)).trigger('input');
    if (typeof jQuery.fn.valid !== 'undefined') {
      $input.valid();
    }
  }

  /**
   * Complete event higher order function.
   *
   * @deprecated 1.6.2
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {Function} Handler function.
   */
  function complete(dz) {
    return function () {
      dz.loading = dz.loading || 0;
      dz.loading--;
      dz.loading = Math.max(dz.loading - 1, 0);
      toggleSubmit(dz);
      updateInputValue(dz);
    };
  }

  /**
   * Add an error message to the current file.
   *
   * @since 1.6.2
   *
   * @param {object} file         File object.
   * @param {string} errorMessage Error message
   */
  function addErrorMessage(file, errorMessage) {
    if (file.isErrorNotUploadedDisplayed) {
      return;
    }
    var span = document.createElement('span');
    span.innerText = errorMessage.toString();
    span.setAttribute('data-dz-errormessage', '');
    file.previewElement.querySelector('.dz-error-message').appendChild(span);
  }

  /**
   * Confirm the upload to the server.
   *
   * The confirmation is needed in order to let PHP know
   * that all the chunks have been uploaded.
   *
   * @since 1.6.2
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {Function} Handler function.
   */
  function confirmChunksFinishUpload(dz) {
    return function confirm(file) {
      if (!file.retries) {
        file.retries = 0;
      }
      if ('error' === file.status) {
        return;
      }

      /**
       * Retry finalize function.
       *
       * @since 1.6.2
       */
      function retry() {
        file.retries++;
        if (file.retries === 3) {
          addErrorMessage(file, window.wpforms_file_upload.errors.file_not_uploaded);
          return;
        }
        setTimeout(function () {
          confirm(file);
        }, 5000 * file.retries);
      }

      /**
       * Fail handler for ajax request.
       *
       * @since 1.6.2
       *
       * @param {object} response Response from the server
       */
      function fail(response) {
        var hasSpecificError = response.responseJSON && response.responseJSON.success === false && response.responseJSON.data;
        if (hasSpecificError) {
          addErrorMessage(file, response.responseJSON.data);
        } else {
          retry();
        }
      }

      /**
       * Handler for ajax request.
       *
       * @since 1.6.2
       *
       * @param {object} response Response from the server
       */
      function complete(response) {
        file.chunkResponse = JSON.stringify({
          data: response
        });
        dz.loading = dz.loading || 0;
        dz.loading--;
        dz.loading = Math.max(dz.loading, 0);
        toggleSubmit(dz);
        updateInputValue(dz);
      }
      wp.ajax.post(jQuery.extend({
        action: 'wpforms_file_chunks_uploaded',
        form_id: dz.dataTransfer.formId,
        field_id: dz.dataTransfer.fieldId,
        name: file.name
      }, dz.options.params.call(dz, null, null, {
        file: file,
        index: 0
      }))).then(complete).fail(fail);

      // Move to upload the next file, if any.
      dz.processQueue();
    };
  }

  /**
   * Toggle showing empty message.
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   */
  function toggleMessage(dz) {
    setTimeout(function () {
      var validFiles = dz.files.filter(function (file) {
        return file.accepted;
      });
      if (validFiles.length >= dz.options.maxFiles) {
        dz.element.querySelector('.dz-message').classList.add('hide');
      } else {
        dz.element.querySelector('.dz-message').classList.remove('hide');
      }
    }, 0);
  }

  /**
   * Toggle error message if total size more than limit.
   * Runs for each file.
   *
   * @since 1.5.6
   *
   * @param {object} file Current file.
   * @param {object} dz   Dropzone object.
   */
  function validatePostMaxSizeError(file, dz) {
    setTimeout(function () {
      if (file.size >= dz.dataTransfer.postMaxSize) {
        var errorMessage = window.wpforms_file_upload.errors.post_max_size;
        if (!file.isErrorNotUploadedDisplayed) {
          file.isErrorNotUploadedDisplayed = true;
          errorMessage = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + errorMessage;
          addErrorMessage(file, errorMessage);
        }
      }
    }, 1);
  }

  /**
   * Start File Upload.
   *
   * This would do the initial request to start a file upload. No chunk
   * is uploaded at this stage, instead all the information related to the
   * file are send to the server waiting for an authorization.
   *
   * If the server authorizes the client would start uploading the chunks.
   *
   * @since 1.6.2
   *
   * @param {object} dz   Dropzone object.
   * @param {object} file Current file.
   */
  function initFileUpload(dz, file) {
    wp.ajax.post(jQuery.extend({
      action: 'wpforms_upload_chunk_init',
      form_id: dz.dataTransfer.formId,
      field_id: dz.dataTransfer.fieldId,
      name: file.name,
      slow: isSlow
    }, dz.options.params.call(dz, null, null, {
      file: file,
      index: 0
    }))).then(function (response) {
      // File upload has been authorized.

      for (var key in response) {
        dz.options[key] = response[key];
      }
      if (response.dzchunksize) {
        dz.options.chunkSize = parseInt(response.dzchunksize, 10);
        file.upload.totalChunkCount = Math.ceil(file.size / dz.options.chunkSize);
      }
      dz.processQueue();
    }).fail(function (response) {
      file.status = 'error';
      if (!file.xhr) {
        var field = dz.element.closest('.wpforms-field');
        var hiddenInput = field.querySelector('.dropzone-input');
        var errorMessage = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + window.wpforms_file_upload.errors.default_error;
        file.previewElement.classList.add('dz-processing', 'dz-error', 'dz-complete');
        hiddenInput.classList.add('wpforms-error');
        field.classList.add('wpforms-has-error');
        addErrorMessage(file, errorMessage);
      }
      dz.processQueue();
    });
  }

  /**
   * Validate the file when it was added in the dropzone.
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {Function} Handler function.
   */
  function addedFile(dz) {
    return function (file) {
      if (file.size >= dz.dataTransfer.postMaxSize) {
        validatePostMaxSizeError(file, dz);
      } else {
        speedTest(function () {
          initFileUpload(dz, file);
        });
      }
      dz.loading = dz.loading || 0;
      dz.loading++;
      toggleSubmit(dz);
      toggleMessage(dz);
    };
  }

  /**
   * Send an AJAX request to remove file from the server.
   *
   * @since 1.5.6
   *
   * @param {string} file File name.
   * @param {object} dz Dropzone object.
   */
  function removeFromServer(file, dz) {
    wp.ajax.post({
      action: 'wpforms_remove_file',
      file: file,
      form_id: dz.dataTransfer.formId,
      field_id: dz.dataTransfer.fieldId
    });
  }

  /**
   * Init the file removal on server when user removed it on front-end.
   *
   * @since 1.5.6
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {Function} Handler function.
   */
  function removedFile(dz) {
    return function (file) {
      toggleMessage(dz);
      var json = file.chunkResponse || (file.xhr || {}).responseText;
      if (json) {
        var object = parseJSON(json);
        if (object && object.data && object.data.file) {
          removeFromServer(object.data.file, dz);
        }
      }

      // Remove submitted value.
      if (Object.prototype.hasOwnProperty.call(file, 'isDefault') && file.isDefault) {
        submittedValues[dz.dataTransfer.formId][dz.dataTransfer.fieldId].splice(file.index, 1);
        dz.options.maxFiles++;
        removeFromServer(file.file, dz);
      }
      updateInputValue(dz);
      dz.loading = dz.loading || 0;
      dz.loading--;
      dz.loading = Math.max(dz.loading, 0);
      toggleSubmit(dz);
      var numErrors = dz.element.querySelectorAll('.dz-preview.dz-error').length;
      if (numErrors === 0) {
        dz.element.classList.remove('wpforms-error');
        dz.element.closest('.wpforms-field').classList.remove('wpforms-has-error');
      }
    };
  }

  /**
   * Process any error that was fired per each file.
   * There might be several errors per file, in that case - display "not uploaded" text only once.
   *
   * @since 1.5.6.1
   *
   * @param {object} dz Dropzone object.
   *
   * @returns {Function} Handler function.
   */
  function error(dz) {
    return function (file, errorMessage) {
      if (file.isErrorNotUploadedDisplayed) {
        return;
      }
      if (_typeof(errorMessage) === 'object') {
        errorMessage = Object.prototype.hasOwnProperty.call(errorMessage, 'data') && typeof errorMessage.data === 'string' ? errorMessage.data : '';
      }
      errorMessage = errorMessage !== '0' ? errorMessage : '';
      file.isErrorNotUploadedDisplayed = true;
      file.previewElement.querySelectorAll('[data-dz-errormessage]')[0].textContent = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + errorMessage;
      dz.element.classList.add('wpforms-error');
      dz.element.closest('.wpforms-field').classList.add('wpforms-has-error');
    };
  }

  /**
   * Preset previously submitted files to the dropzone.
   *
   * @since 1.7.1
   *
   * @param {object} dz Dropzone object.
   */
  function presetSubmittedData(dz) {
    var files = parseJSON(getInput(dz).val());
    if (!files || !files.length) {
      return;
    }
    submittedValues[dz.dataTransfer.formId] = [];

    // We do deep cloning an object to be sure that data is passed without links.
    submittedValues[dz.dataTransfer.formId][dz.dataTransfer.fieldId] = JSON.parse(JSON.stringify(files));
    files.forEach(function (file, index) {
      file.isDefault = true;
      file.index = index;
      if (file.type.match(/image.*/)) {
        dz.displayExistingFile(file, file.url);
        return;
      }
      dz.emit('addedfile', file);
      dz.emit('complete', file);
    });
    dz.options.maxFiles = dz.options.maxFiles - files.length;
  }

  /**
   * Dropzone.js init for each field.
   *
   * @since 1.5.6
   *
   * @param {object} $el WPForms uploader DOM element.
   *
   * @returns {object} Dropzone object.
   */
  function dropZoneInit($el) {
    if ($el.dropzone) {
      return $el.dropzone;
    }
    var formId = parseInt($el.dataset.formId, 10);
    var fieldId = parseInt($el.dataset.fieldId, 10) || 0;
    var maxFiles = parseInt($el.dataset.maxFileNumber, 10);
    var acceptedFiles = $el.dataset.extensions.split(',').map(function (el) {
      return '.' + el;
    }).join(',');

    // Configure and modify Dropzone library.
    var dz = new window.Dropzone($el, {
      url: window.wpforms_file_upload.url,
      addRemoveLinks: true,
      chunking: true,
      forceChunking: true,
      retryChunks: true,
      chunkSize: parseInt($el.dataset.fileChunkSize, 10),
      paramName: $el.dataset.inputName,
      parallelChunkUploads: !!($el.dataset.parallelUploads || '').match(/^true$/i),
      parallelUploads: parseInt($el.dataset.maxParallelUploads, 10),
      autoProcessQueue: false,
      maxFilesize: (parseInt($el.dataset.maxSize, 10) / (1024 * 1024)).toFixed(2),
      maxFiles: maxFiles,
      acceptedFiles: acceptedFiles,
      dictMaxFilesExceeded: window.wpforms_file_upload.errors.file_limit.replace('{fileLimit}', maxFiles),
      dictInvalidFileType: window.wpforms_file_upload.errors.file_extension,
      dictFileTooBig: window.wpforms_file_upload.errors.file_size
    });

    // Custom variables.
    dz.dataTransfer = {
      postMaxSize: $el.dataset.maxSize,
      name: $el.dataset.inputName,
      formId: formId,
      fieldId: fieldId
    };
    presetSubmittedData(dz);

    // Process events.
    dz.on('sending', sending(dz, {
      action: 'wpforms_upload_chunk',
      form_id: formId,
      field_id: fieldId
    }));
    dz.on('addedfile', addedFile(dz));
    dz.on('removedfile', removedFile(dz));
    dz.on('complete', confirmChunksFinishUpload(dz));
    dz.on('error', error(dz));
    return dz;
  }

  /**
   * Hidden Dropzone input focus event handler.
   *
   * @since 1.8.1
   */
  function dropzoneInputFocus() {
    $(this).prev('.wpforms-uploader').addClass('wpforms-focus');
  }

  /**
   * Hidden Dropzone input blur event handler.
   *
   * @since 1.8.1
   */
  function dropzoneInputBlur() {
    $(this).prev('.wpforms-uploader').removeClass('wpforms-focus');
  }

  /**
   * Hidden Dropzone input blur event handler.
   *
   * @since 1.8.1
   *
   * @param {object} e Event object.
   */
  function dropzoneInputKeypress(e) {
    e.preventDefault();
    if (e.keyCode !== 13) {
      return;
    }
    $(this).prev('.wpforms-uploader').trigger('click');
  }

  /**
   * Hidden Dropzone input blur event handler.
   *
   * @since 1.8.1
   */
  function dropzoneClick() {
    $(this).next('.dropzone-input').trigger('focus');
  }

  /**
   * Classic File upload success callback to determine if all files are uploaded.
   *
   * @since 1.8.3
   *
   * @param {Event} e Event.
   * @param {jQuery} $form Form.
   */
  function combinedUploadsSizeOk(e, $form) {
    if (anyUploadsInProgress()) {
      disableSubmitButton($form);
    }
  }

  /**
   * Events.
   *
   * @since 1.8.1
   */
  function events() {
    $('.dropzone-input').on('focus', dropzoneInputFocus).on('blur', dropzoneInputBlur).on('keypress', dropzoneInputKeypress);
    $('.wpforms-uploader').on('click', dropzoneClick);
    $('form.wpforms-form').on('wpformsCombinedUploadsSizeOk', combinedUploadsSizeOk);
  }

  /**
   * DOMContentLoaded handler.
   *
   * @since 1.5.6
   */
  function ready() {
    window.wpforms = window.wpforms || {};
    window.wpforms.dropzones = [].slice.call(document.querySelectorAll('.wpforms-uploader')).map(dropZoneInit);
    events();
  }

  /**
   * Modern File Upload engine.
   *
   * @since 1.6.0
   */
  var wpformsModernFileUpload = {
    /**
     * Start the initialization.
     *
     * @since 1.6.0
     */
    init: function init() {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
      } else {
        ready();
      }
    }
  };

  // Call init and save in global variable.
  wpformsModernFileUpload.init();
  window.wpformsModernFileUpload = wpformsModernFileUpload;
})(jQuery);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyJfdHlwZW9mIiwibyIsIlN5bWJvbCIsIml0ZXJhdG9yIiwiY29uc3RydWN0b3IiLCJwcm90b3R5cGUiLCIkIiwiaXNTbG93Iiwic3VibWl0dGVkVmFsdWVzIiwic3BlZWRUZXN0U2V0dGluZ3MiLCJtYXhUaW1lIiwicGF5bG9hZFNpemUiLCJnZXRQYXlsb2FkIiwiZGF0YSIsImkiLCJTdHJpbmciLCJmcm9tQ2hhckNvZGUiLCJNYXRoIiwicm91bmQiLCJyYW5kb20iLCJzcGVlZFRlc3QiLCJuZXh0Iiwic2V0VGltZW91dCIsInN0YXJ0IiwiRGF0ZSIsIndwIiwiYWpheCIsInBvc3QiLCJhY3Rpb24iLCJ0aGVuIiwiZGVsdGEiLCJmYWlsIiwidG9nZ2xlTG9hZGluZ01lc3NhZ2UiLCIkZm9ybSIsImZpbmQiLCJsZW5ndGgiLCJiZWZvcmUiLCJjb25jYXQiLCJ3aW5kb3ciLCJ3cGZvcm1zX2ZpbGVfdXBsb2FkIiwibG9hZGluZ19tZXNzYWdlIiwidXBsb2FkSW5Qcm9ncmVzcyIsImR6IiwibG9hZGluZyIsImdldEZpbGVzV2l0aFN0YXR1cyIsImFueVVwbG9hZHNJblByb2dyZXNzIiwid3Bmb3JtcyIsImRyb3B6b25lcyIsInNvbWUiLCJkaXNhYmxlU3VibWl0QnV0dG9uIiwiJGJ0biIsIiRidG5OZXh0IiwiaGFuZGxlciIsInByb3AiLCJXUEZvcm1zVXRpbHMiLCJ0cmlnZ2VyRXZlbnQiLCJhdHRyIiwicGFyZW50IiwiYWRkQ2xhc3MiLCJhcHBlbmQiLCJjc3MiLCJ3aWR0aCIsIm91dGVyV2lkdGgiLCJoZWlnaHQiLCJvdXRlckhlaWdodCIsIm9uIiwidG9nZ2xlU3VibWl0IiwialF1ZXJ5IiwiZWxlbWVudCIsImNsb3Nlc3QiLCJkaXNhYmxlZCIsImlzQnV0dG9uRGlzYWJsZWQiLCJCb29sZWFuIiwiaGFzQ2xhc3MiLCJvZmYiLCJyZW1vdmUiLCJyZW1vdmVDbGFzcyIsInBhcnNlSlNPTiIsInN0ciIsIkpTT04iLCJwYXJzZSIsImUiLCJvbmx5V2l0aExlbmd0aCIsImVsIiwib25seVBvc2l0aXZlIiwiZ2V0WEhSIiwiY2h1bmtSZXNwb25zZSIsInhociIsImdldFJlc3BvbnNlVGV4dCIsInJlc3BvbnNlVGV4dCIsImdldERhdGEiLCJnZXRWYWx1ZSIsImZpbGVzIiwibWFwIiwiZmlsdGVyIiwic2VuZGluZyIsImZpbGUiLCJmb3JtRGF0YSIsInNpemUiLCJkYXRhVHJhbnNmZXIiLCJwb3N0TWF4U2l6ZSIsInNlbmQiLCJhY2NlcHRlZCIsInByb2Nlc3NpbmciLCJzdGF0dXMiLCJwcmV2aWV3RWxlbWVudCIsImNsYXNzTGlzdCIsImFkZCIsIk9iamVjdCIsImtleXMiLCJmb3JFYWNoIiwia2V5IiwiY29udmVydEZpbGVzVG9WYWx1ZSIsImZvcm1JZCIsImZpZWxkSWQiLCJzdHJpbmdpZnkiLCJwdXNoIiwiYXBwbHkiLCJnZXRJbnB1dCIsInBhcmVudHMiLCJuYW1lIiwidXBkYXRlSW5wdXRWYWx1ZSIsIiRpbnB1dCIsInZhbCIsInRyaWdnZXIiLCJmbiIsInZhbGlkIiwiY29tcGxldGUiLCJtYXgiLCJhZGRFcnJvck1lc3NhZ2UiLCJlcnJvck1lc3NhZ2UiLCJpc0Vycm9yTm90VXBsb2FkZWREaXNwbGF5ZWQiLCJzcGFuIiwiZG9jdW1lbnQiLCJjcmVhdGVFbGVtZW50IiwiaW5uZXJUZXh0IiwidG9TdHJpbmciLCJzZXRBdHRyaWJ1dGUiLCJxdWVyeVNlbGVjdG9yIiwiYXBwZW5kQ2hpbGQiLCJjb25maXJtQ2h1bmtzRmluaXNoVXBsb2FkIiwiY29uZmlybSIsInJldHJpZXMiLCJyZXRyeSIsImVycm9ycyIsImZpbGVfbm90X3VwbG9hZGVkIiwicmVzcG9uc2UiLCJoYXNTcGVjaWZpY0Vycm9yIiwicmVzcG9uc2VKU09OIiwic3VjY2VzcyIsImV4dGVuZCIsImZvcm1faWQiLCJmaWVsZF9pZCIsIm9wdGlvbnMiLCJwYXJhbXMiLCJjYWxsIiwiaW5kZXgiLCJwcm9jZXNzUXVldWUiLCJ0b2dnbGVNZXNzYWdlIiwidmFsaWRGaWxlcyIsIm1heEZpbGVzIiwidmFsaWRhdGVQb3N0TWF4U2l6ZUVycm9yIiwicG9zdF9tYXhfc2l6ZSIsImluaXRGaWxlVXBsb2FkIiwic2xvdyIsImR6Y2h1bmtzaXplIiwiY2h1bmtTaXplIiwicGFyc2VJbnQiLCJ1cGxvYWQiLCJ0b3RhbENodW5rQ291bnQiLCJjZWlsIiwiZmllbGQiLCJoaWRkZW5JbnB1dCIsImRlZmF1bHRfZXJyb3IiLCJhZGRlZEZpbGUiLCJyZW1vdmVGcm9tU2VydmVyIiwicmVtb3ZlZEZpbGUiLCJqc29uIiwib2JqZWN0IiwiaGFzT3duUHJvcGVydHkiLCJpc0RlZmF1bHQiLCJzcGxpY2UiLCJudW1FcnJvcnMiLCJxdWVyeVNlbGVjdG9yQWxsIiwiZXJyb3IiLCJ0ZXh0Q29udGVudCIsInByZXNldFN1Ym1pdHRlZERhdGEiLCJ0eXBlIiwibWF0Y2giLCJkaXNwbGF5RXhpc3RpbmdGaWxlIiwidXJsIiwiZW1pdCIsImRyb3Bab25lSW5pdCIsIiRlbCIsImRyb3B6b25lIiwiZGF0YXNldCIsIm1heEZpbGVOdW1iZXIiLCJhY2NlcHRlZEZpbGVzIiwiZXh0ZW5zaW9ucyIsInNwbGl0Iiwiam9pbiIsIkRyb3B6b25lIiwiYWRkUmVtb3ZlTGlua3MiLCJjaHVua2luZyIsImZvcmNlQ2h1bmtpbmciLCJyZXRyeUNodW5rcyIsImZpbGVDaHVua1NpemUiLCJwYXJhbU5hbWUiLCJpbnB1dE5hbWUiLCJwYXJhbGxlbENodW5rVXBsb2FkcyIsInBhcmFsbGVsVXBsb2FkcyIsIm1heFBhcmFsbGVsVXBsb2FkcyIsImF1dG9Qcm9jZXNzUXVldWUiLCJtYXhGaWxlc2l6ZSIsIm1heFNpemUiLCJ0b0ZpeGVkIiwiZGljdE1heEZpbGVzRXhjZWVkZWQiLCJmaWxlX2xpbWl0IiwicmVwbGFjZSIsImRpY3RJbnZhbGlkRmlsZVR5cGUiLCJmaWxlX2V4dGVuc2lvbiIsImRpY3RGaWxlVG9vQmlnIiwiZmlsZV9zaXplIiwiZHJvcHpvbmVJbnB1dEZvY3VzIiwicHJldiIsImRyb3B6b25lSW5wdXRCbHVyIiwiZHJvcHpvbmVJbnB1dEtleXByZXNzIiwicHJldmVudERlZmF1bHQiLCJrZXlDb2RlIiwiZHJvcHpvbmVDbGljayIsImNvbWJpbmVkVXBsb2Fkc1NpemVPayIsImV2ZW50cyIsInJlYWR5Iiwic2xpY2UiLCJ3cGZvcm1zTW9kZXJuRmlsZVVwbG9hZCIsImluaXQiLCJyZWFkeVN0YXRlIiwiYWRkRXZlbnRMaXN0ZW5lciJdLCJzb3VyY2VzIjpbImZha2VfNTg5YTAzLmpzIl0sInNvdXJjZXNDb250ZW50IjpbIi8qIGdsb2JhbCBXUEZvcm1zVXRpbHMgKi9cbid1c2Ugc3RyaWN0JztcblxuKCBmdW5jdGlvbiggJCApIHtcblxuXHQvKipcblx0ICogQWxsIGNvbm5lY3Rpb25zIGFyZSBzbG93IGJ5IGRlZmF1bHQuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjYuMlxuXHQgKlxuXHQgKiBAdHlwZSB7Ym9vbGVhbnxudWxsfVxuXHQgKi9cblx0dmFyIGlzU2xvdyA9IG51bGw7XG5cblx0LyoqXG5cdCAqIFByZXZpb3VzbHkgc3VibWl0dGVkIGRhdGEuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjcuMVxuXHQgKlxuXHQgKiBAdHlwZSB7QXJyYXl9XG5cdCAqL1xuXHR2YXIgc3VibWl0dGVkVmFsdWVzID0gW107XG5cblx0LyoqXG5cdCAqIERlZmF1bHQgc2V0dGluZ3MgZm9yIG91ciBzcGVlZCB0ZXN0LlxuXHQgKlxuXHQgKiBAc2luY2UgMS42LjJcblx0ICpcblx0ICogQHR5cGUge3ttYXhUaW1lOiBudW1iZXIsIHBheWxvYWRTaXplOiBudW1iZXJ9fVxuXHQgKi9cblx0dmFyIHNwZWVkVGVzdFNldHRpbmdzID0ge1xuXHRcdG1heFRpbWU6IDMwMDAsIC8vIE1heCB0aW1lIChtcykgaXQgc2hvdWxkIHRha2UgdG8gYmUgY29uc2lkZXJlZCBhICdmYXN0IGNvbm5lY3Rpb24nLlxuXHRcdHBheWxvYWRTaXplOiAxMDAgKiAxMDI0LCAvLyBQYXlsb2FkIHNpemUuXG5cdH07XG5cblx0LyoqXG5cdCAqIENyZWF0ZSBhIHJhbmRvbSBwYXlsb2FkIGZvciB0aGUgc3BlZWQgdGVzdC5cblx0ICpcblx0ICogQHNpbmNlIDEuNi4yXG5cdCAqXG5cdCAqIEByZXR1cm5zIHtzdHJpbmd9IFJhbmRvbSBwYXlsb2FkLlxuXHQgKi9cblx0ZnVuY3Rpb24gZ2V0UGF5bG9hZCgpIHtcblxuXHRcdHZhciBkYXRhID0gJyc7XG5cblx0XHRmb3IgKCB2YXIgaSA9IDA7IGkgPCBzcGVlZFRlc3RTZXR0aW5ncy5wYXlsb2FkU2l6ZTsgKytpICkge1xuXHRcdFx0ZGF0YSArPSBTdHJpbmcuZnJvbUNoYXJDb2RlKCBNYXRoLnJvdW5kKCBNYXRoLnJhbmRvbSgpICogMzYgKyA2NCApICk7XG5cdFx0fVxuXG5cdFx0cmV0dXJuIGRhdGE7XG5cdH1cblxuXHQvKipcblx0ICogUnVuIHNwZWVkIHRlc3RzIGFuZCBmbGFnIHRoZSBjbGllbnRzIGFzIHNsb3cgb3Igbm90LiBJZiBhIGNvbm5lY3Rpb25cblx0ICogaXMgc2xvdyBpdCB3b3VsZCBsZXQgdGhlIGJhY2tlbmQga25vdyBhbmQgdGhlIGJhY2tlbmQgbW9zdCBsaWtlbHlcblx0ICogd291bGQgZGlzYWJsZSBwYXJhbGxlbCB1cGxvYWRzIGFuZCB3b3VsZCBzZXQgc21hbGxlciBjaHVuayBzaXplcy5cblx0ICpcblx0ICogQHNpbmNlIDEuNi4yXG5cdCAqXG5cdCAqIEBwYXJhbSB7RnVuY3Rpb259IG5leHQgRnVuY3Rpb24gdG8gY2FsbCB3aGVuIHRoZSBzcGVlZCBkZXRlY3Rpb24gaXMgZG9uZS5cblx0ICovXG5cdGZ1bmN0aW9uIHNwZWVkVGVzdCggbmV4dCApIHtcblxuXHRcdGlmICggbnVsbCAhPT0gaXNTbG93ICkge1xuXHRcdFx0c2V0VGltZW91dCggbmV4dCApO1xuXHRcdFx0cmV0dXJuO1xuXHRcdH1cblxuXHRcdHZhciBkYXRhICA9IGdldFBheWxvYWQoKTtcblx0XHR2YXIgc3RhcnQgPSBuZXcgRGF0ZTtcblxuXHRcdHdwLmFqYXgucG9zdCgge1xuXHRcdFx0YWN0aW9uOiAnd3Bmb3Jtc19maWxlX3VwbG9hZF9zcGVlZF90ZXN0Jyxcblx0XHRcdGRhdGE6IGRhdGEsXG5cdFx0fSApLnRoZW4oIGZ1bmN0aW9uKCkge1xuXG5cdFx0XHR2YXIgZGVsdGEgPSBuZXcgRGF0ZSAtIHN0YXJ0O1xuXG5cdFx0XHRpc1Nsb3cgPSBkZWx0YSA+PSBzcGVlZFRlc3RTZXR0aW5ncy5tYXhUaW1lO1xuXG5cdFx0XHRuZXh0KCk7XG5cdFx0fSApLmZhaWwoIGZ1bmN0aW9uKCkge1xuXG5cdFx0XHRpc1Nsb3cgPSB0cnVlO1xuXG5cdFx0XHRuZXh0KCk7XG5cdFx0fSApO1xuXHR9XG5cblx0LyoqXG5cdCAqIFRvZ2dsZSBsb2FkaW5nIG1lc3NhZ2UgYWJvdmUgc3VibWl0IGJ1dHRvbi5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSAkZm9ybSBqUXVlcnkgZm9ybSBlbGVtZW50LlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7RnVuY3Rpb259IGV2ZW50IGhhbmRsZXIgZnVuY3Rpb24uXG5cdCAqL1xuXHRmdW5jdGlvbiB0b2dnbGVMb2FkaW5nTWVzc2FnZSggJGZvcm0gKSB7XG5cblx0XHRyZXR1cm4gZnVuY3Rpb24oKSB7XG5cblx0XHRcdGlmICggJGZvcm0uZmluZCggJy53cGZvcm1zLXVwbG9hZGluZy1pbi1wcm9ncmVzcy1hbGVydCcgKS5sZW5ndGggKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0JGZvcm0uZmluZCggJy53cGZvcm1zLXN1Ym1pdC1jb250YWluZXInIClcblx0XHRcdFx0LmJlZm9yZShcblx0XHRcdFx0XHRgPGRpdiBjbGFzcz1cIndwZm9ybXMtZXJyb3ItYWxlcnQgd3Bmb3Jtcy11cGxvYWRpbmctaW4tcHJvZ3Jlc3MtYWxlcnRcIj5cblx0XHRcdFx0XHRcdCR7d2luZG93LndwZm9ybXNfZmlsZV91cGxvYWQubG9hZGluZ19tZXNzYWdlfVxuXHRcdFx0XHRcdDwvZGl2PmBcblx0XHRcdFx0KTtcblx0XHR9O1xuXHR9XG5cblx0LyoqXG5cdCAqIElzIGEgZmllbGQgbG9hZGluZz9cblx0ICpcblx0ICogQHNpbmNlIDEuNy42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkeiBEcm9wem9uZSBvYmplY3QuXG5cdCAqXG5cdCAqIEByZXR1cm5zIHtib29sZWFufSB0cnVlIGlmIHRoZSBmaWVsZCBpcyBsb2FkaW5nLlxuXHQgKi9cblx0ZnVuY3Rpb24gdXBsb2FkSW5Qcm9ncmVzcyggZHogKSB7XG5cblx0XHRyZXR1cm4gZHoubG9hZGluZyA+IDAgfHwgZHouZ2V0RmlsZXNXaXRoU3RhdHVzKCAnZXJyb3InICkubGVuZ3RoID4gMDtcblx0fVxuXG5cdC8qKlxuXHQgKiBJcyBhdCBsZWFzdCBvbmUgZmllbGQgbG9hZGluZz9cblx0ICpcblx0ICogQHNpbmNlIDEuNy42XG5cdCAqXG5cdCAqIEByZXR1cm5zIHtib29sZWFufSB0cnVlIGlmIGF0IGxlYXN0IG9uZSBmaWVsZCBpcyBsb2FkaW5nLlxuXHQgKi9cblx0ZnVuY3Rpb24gYW55VXBsb2Fkc0luUHJvZ3Jlc3MoKSB7XG5cblx0XHR2YXIgYW55VXBsb2Fkc0luUHJvZ3Jlc3MgPSBmYWxzZTtcblxuXHRcdHdpbmRvdy53cGZvcm1zLmRyb3B6b25lcy5zb21lKCBmdW5jdGlvbiggZHogKSB7XG5cblx0XHRcdGlmICggdXBsb2FkSW5Qcm9ncmVzcyggZHogKSApIHtcblx0XHRcdFx0YW55VXBsb2Fkc0luUHJvZ3Jlc3MgPSB0cnVlO1xuXG5cdFx0XHRcdHJldHVybiB0cnVlO1xuXHRcdFx0fVxuXHRcdH0gKTtcblxuXHRcdHJldHVybiBhbnlVcGxvYWRzSW5Qcm9ncmVzcztcblx0fVxuXG5cdC8qKlxuXHQgKiBEaXNhYmxlIHN1Ym1pdCBidXR0b24gYW5kIGFkZCBvdmVybGF5LlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gJGZvcm0galF1ZXJ5IGZvcm0gZWxlbWVudC5cblx0ICovXG5cdGZ1bmN0aW9uIGRpc2FibGVTdWJtaXRCdXR0b24oICRmb3JtICkge1xuXG5cdFx0Ly8gRmluZCB0aGUgcHJpbWFyeSBzdWJtaXQgYnV0dG9uIGFuZCB0aGUgXCJOZXh0XCIgYnV0dG9uIGZvciBtdWx0aS1wYWdlIGZvcm1zLlxuXHRcdGxldCAkYnRuID0gJGZvcm0uZmluZCggJy53cGZvcm1zLXN1Ym1pdCcgKTtcblx0XHRjb25zdCAkYnRuTmV4dCA9ICRmb3JtLmZpbmQoICcud3Bmb3Jtcy1wYWdlLW5leHQ6dmlzaWJsZScgKTtcblx0XHRjb25zdCBoYW5kbGVyID0gdG9nZ2xlTG9hZGluZ01lc3NhZ2UoICRmb3JtICk7IC8vIEdldCB0aGUgaGFuZGxlciBmdW5jdGlvbiBmb3IgbG9hZGluZyBtZXNzYWdlIHRvZ2dsZS5cblxuXHRcdC8vIEZvciBtdWx0aS1wYWdlcyBsYXlvdXQsIHVzZSB0aGUgXCJOZXh0XCIgYnV0dG9uIGluc3RlYWQgb2YgdGhlIHByaW1hcnkgc3VibWl0IGJ1dHRvbi5cblx0XHRpZiAoICRmb3JtLmZpbmQoICcud3Bmb3Jtcy1wYWdlLWluZGljYXRvcicgKS5sZW5ndGggIT09IDAgJiYgJGJ0bk5leHQubGVuZ3RoICE9PSAwICkge1xuXHRcdFx0JGJ0biA9ICRidG5OZXh0O1xuXHRcdH1cblxuXHRcdC8vIERpc2FibGUgdGhlIHN1Ym1pdCBidXR0b24uXG5cdFx0JGJ0bi5wcm9wKCAnZGlzYWJsZWQnLCB0cnVlICk7XG5cdFx0V1BGb3Jtc1V0aWxzLnRyaWdnZXJFdmVudCggJGZvcm0sICd3cGZvcm1zRm9ybVN1Ym1pdEJ1dHRvbkRpc2FibGUnLCBbICRmb3JtLCAkYnRuIF0gKTtcblxuXHRcdC8vIElmIHRoZSBvdmVybGF5IGlzIG5vdCBhbHJlYWR5IGFkZGVkIGFuZCB0aGUgYnV0dG9uIGlzIG9mIHR5cGUgXCJzdWJtaXRcIiwgYWRkIGFuIG92ZXJsYXkuXG5cdFx0aWYgKCAhICRmb3JtLmZpbmQoICcud3Bmb3Jtcy1zdWJtaXQtb3ZlcmxheScgKS5sZW5ndGggJiYgJGJ0bi5hdHRyKCAndHlwZScgKSA9PT0gJ3N1Ym1pdCcgKSB7XG5cblx0XHRcdC8vIEFkZCBhIGNvbnRhaW5lciBmb3IgdGhlIG92ZXJsYXkgYW5kIGFwcGVuZCB0aGUgb3ZlcmxheSBlbGVtZW50IHRvIGl0LlxuXHRcdFx0JGJ0bi5wYXJlbnQoKS5hZGRDbGFzcyggJ3dwZm9ybXMtc3VibWl0LW92ZXJsYXktY29udGFpbmVyJyApO1xuXHRcdFx0JGJ0bi5wYXJlbnQoKS5hcHBlbmQoICc8ZGl2IGNsYXNzPVwid3Bmb3Jtcy1zdWJtaXQtb3ZlcmxheVwiPjwvZGl2PicgKTtcblxuXHRcdFx0Ly8gU2V0IHRoZSBvdmVybGF5IGRpbWVuc2lvbnMgdG8gbWF0Y2ggdGhlIHN1Ym1pdCBidXR0b24ncyBzaXplLlxuXHRcdFx0JGZvcm0uZmluZCggJy53cGZvcm1zLXN1Ym1pdC1vdmVybGF5JyApLmNzcygge1xuXHRcdFx0XHR3aWR0aDogYCR7JGJ0bi5vdXRlcldpZHRoKCl9cHhgLFxuXHRcdFx0XHRoZWlnaHQ6IGAkeyRidG4ucGFyZW50KCkub3V0ZXJIZWlnaHQoKX1weGAsXG5cdFx0XHR9ICk7XG5cblx0XHRcdC8vIEF0dGFjaCB0aGUgY2xpY2sgZXZlbnQgdG8gdGhlIG92ZXJsYXkgc28gdGhhdCBpdCB0cmlnZ2VycyB0aGUgaGFuZGxlciBmdW5jdGlvbi5cblx0XHRcdCRmb3JtLmZpbmQoICcud3Bmb3Jtcy1zdWJtaXQtb3ZlcmxheScgKS5vbiggJ2NsaWNrJywgaGFuZGxlciApO1xuXHRcdH1cblx0fVxuXG5cdC8qKlxuXHQgKiBEaXNhYmxlIHN1Ym1pdCBidXR0b24gd2hlbiB3ZSBhcmUgc2VuZGluZyBmaWxlcyB0byB0aGUgc2VydmVyLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGR6IERyb3B6b25lIG9iamVjdC5cblx0ICovXG5cdGZ1bmN0aW9uIHRvZ2dsZVN1Ym1pdCggZHogKSB7IC8vIGVzbGludC1kaXNhYmxlLWxpbmUgY29tcGxleGl0eVxuXG5cdFx0dmFyICRmb3JtID0galF1ZXJ5KCBkei5lbGVtZW50ICkuY2xvc2VzdCggJ2Zvcm0nICksXG5cdFx0XHQkYnRuID0gJGZvcm0uZmluZCggJy53cGZvcm1zLXN1Ym1pdCcgKSxcblx0XHRcdCRidG5OZXh0ID0gJGZvcm0uZmluZCggJy53cGZvcm1zLXBhZ2UtbmV4dDp2aXNpYmxlJyApLFxuXHRcdFx0aGFuZGxlciA9IHRvZ2dsZUxvYWRpbmdNZXNzYWdlKCAkZm9ybSApLFxuXHRcdFx0ZGlzYWJsZWQgPSB1cGxvYWRJblByb2dyZXNzKCBkeiApO1xuXG5cdFx0Ly8gRm9yIG11bHRpLXBhZ2VzIGxheW91dC5cblx0XHRpZiAoICRmb3JtLmZpbmQoICcud3Bmb3Jtcy1wYWdlLWluZGljYXRvcicgKS5sZW5ndGggIT09IDAgJiYgJGJ0bk5leHQubGVuZ3RoICE9PSAwICkge1xuXHRcdFx0JGJ0biA9ICRidG5OZXh0O1xuXHRcdH1cblxuXHRcdGNvbnN0IGlzQnV0dG9uRGlzYWJsZWQgPSBCb29sZWFuKCAkYnRuLnByb3AoICdkaXNhYmxlZCcgKSApIHx8ICRidG4uaGFzQ2xhc3MoICd3cGZvcm1zLWRpc2FibGVkJyApO1xuXG5cdFx0aWYgKCBkaXNhYmxlZCA9PT0gaXNCdXR0b25EaXNhYmxlZCApIHtcblx0XHRcdHJldHVybjtcblx0XHR9XG5cblx0XHRpZiAoIGRpc2FibGVkICkge1xuXHRcdFx0ZGlzYWJsZVN1Ym1pdEJ1dHRvbiggJGZvcm0gKTtcblx0XHRcdHJldHVybjtcblx0XHR9XG5cblx0XHRpZiAoIGFueVVwbG9hZHNJblByb2dyZXNzKCkgKSB7XG5cdFx0XHRyZXR1cm47XG5cdFx0fVxuXG5cdFx0JGJ0bi5wcm9wKCAnZGlzYWJsZWQnLCBmYWxzZSApO1xuXHRcdFdQRm9ybXNVdGlscy50cmlnZ2VyRXZlbnQoICRmb3JtLCAnd3Bmb3Jtc0Zvcm1TdWJtaXRCdXR0b25SZXN0b3JlJywgWyAkZm9ybSwgJGJ0biBdICk7XG5cdFx0JGZvcm0uZmluZCggJy53cGZvcm1zLXN1Ym1pdC1vdmVybGF5JyApLm9mZiggJ2NsaWNrJywgaGFuZGxlciApO1xuXHRcdCRmb3JtLmZpbmQoICcud3Bmb3Jtcy1zdWJtaXQtb3ZlcmxheScgKS5yZW1vdmUoKTtcblx0XHQkYnRuLnBhcmVudCgpLnJlbW92ZUNsYXNzKCAnd3Bmb3Jtcy1zdWJtaXQtb3ZlcmxheS1jb250YWluZXInICk7XG5cdFx0aWYgKCAkZm9ybS5maW5kKCAnLndwZm9ybXMtdXBsb2FkaW5nLWluLXByb2dyZXNzLWFsZXJ0JyApLmxlbmd0aCApIHtcblx0XHRcdCRmb3JtLmZpbmQoICcud3Bmb3Jtcy11cGxvYWRpbmctaW4tcHJvZ3Jlc3MtYWxlcnQnICkucmVtb3ZlKCk7XG5cdFx0fVxuXHR9XG5cblx0LyoqXG5cdCAqIFRyeSB0byBwYXJzZSBKU09OIG9yIHJldHVybiBmYWxzZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7c3RyaW5nfSBzdHIgSlNPTiBzdHJpbmcgY2FuZGlkYXRlLlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7Kn0gUGFyc2Ugb2JqZWN0IG9yIGZhbHNlLlxuXHQgKi9cblx0ZnVuY3Rpb24gcGFyc2VKU09OKCBzdHIgKSB7XG5cdFx0dHJ5IHtcblx0XHRcdHJldHVybiBKU09OLnBhcnNlKCBzdHIgKTtcblx0XHR9IGNhdGNoICggZSApIHtcblx0XHRcdHJldHVybiBmYWxzZTtcblx0XHR9XG5cdH1cblxuXHQvKipcblx0ICogTGVhdmUgb25seSBvYmplY3RzIHdpdGggbGVuZ3RoLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGVsIEFueSBhcnJheS5cblx0ICpcblx0ICogQHJldHVybnMge2Jvb2x9IEhhcyBsZW5ndGggbW9yZSB0aGFuIDAgb3Igbm8uXG5cdCAqL1xuXHRmdW5jdGlvbiBvbmx5V2l0aExlbmd0aCggZWwgKSB7XG5cdFx0cmV0dXJuIGVsLmxlbmd0aCA+IDA7XG5cdH1cblxuXHQvKipcblx0ICogTGVhdmUgb25seSBwb3NpdGl2ZSBlbGVtZW50cy5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7Kn0gZWwgQW55IGVsZW1lbnQuXG5cdCAqXG5cdCAqIEByZXR1cm5zIHsqfSBGaWx0ZXIgb25seSBwb3NpdGl2ZS5cblx0ICovXG5cdGZ1bmN0aW9uIG9ubHlQb3NpdGl2ZSggZWwgKSB7XG5cdFx0cmV0dXJuIGVsO1xuXHR9XG5cblx0LyoqXG5cdCAqIEdldCB4aHIuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjUuNlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZWwgT2JqZWN0IHdpdGggeGhyIHByb3BlcnR5LlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7Kn0gR2V0IFhIUi5cblx0ICovXG5cdGZ1bmN0aW9uIGdldFhIUiggZWwgKSB7XG5cdFx0cmV0dXJuIGVsLmNodW5rUmVzcG9uc2UgfHwgZWwueGhyO1xuXHR9XG5cblx0LyoqXG5cdCAqIEdldCByZXNwb25zZSB0ZXh0LlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGVsIFhociBvYmplY3QuXG5cdCAqXG5cdCAqIEByZXR1cm5zIHtvYmplY3R9IFJlc3BvbnNlIHRleHQuXG5cdCAqL1xuXHRmdW5jdGlvbiBnZXRSZXNwb25zZVRleHQoIGVsICkge1xuXHRcdHJldHVybiB0eXBlb2YgZWwgPT09ICdzdHJpbmcnID8gZWwgOiBlbC5yZXNwb25zZVRleHQ7XG5cdH1cblxuXHQvKipcblx0ICogR2V0IGRhdGEuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjUuNlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZWwgT2JqZWN0IHdpdGggZGF0YSBwcm9wZXJ0eS5cblx0ICpcblx0ICogQHJldHVybnMge29iamVjdH0gRGF0YS5cblx0ICovXG5cdGZ1bmN0aW9uIGdldERhdGEoIGVsICkge1xuXHRcdHJldHVybiBlbC5kYXRhO1xuXHR9XG5cblx0LyoqXG5cdCAqIEdldCB2YWx1ZSBmcm9tIGZpbGVzLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGZpbGVzIERyb3B6b25lIGZpbGVzLlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7b2JqZWN0fSBQcmVwYXJlZCB2YWx1ZS5cblx0ICovXG5cdGZ1bmN0aW9uIGdldFZhbHVlKCBmaWxlcyApIHtcblx0XHRyZXR1cm4gZmlsZXNcblx0XHRcdC5tYXAoIGdldFhIUiApXG5cdFx0XHQuZmlsdGVyKCBvbmx5UG9zaXRpdmUgKVxuXHRcdFx0Lm1hcCggZ2V0UmVzcG9uc2VUZXh0IClcblx0XHRcdC5maWx0ZXIoIG9ubHlXaXRoTGVuZ3RoIClcblx0XHRcdC5tYXAoIHBhcnNlSlNPTiApXG5cdFx0XHQuZmlsdGVyKCBvbmx5UG9zaXRpdmUgKVxuXHRcdFx0Lm1hcCggZ2V0RGF0YSApO1xuXHR9XG5cblx0LyoqXG5cdCAqIFNlbmRpbmcgZXZlbnQgaGlnaGVyIG9yZGVyIGZ1bmN0aW9uLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICogQHNpbmNlIDEuNS42LjEgQWRkZWQgc3BlY2lhbCBwcm9jZXNzaW5nIG9mIGEgZmlsZSB0aGF0IGlzIGxhcmdlciB0aGFuIHNlcnZlcidzIHBvc3RfbWF4X3NpemUuXG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkeiBEcm9wem9uZSBvYmplY3QuXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkYXRhIEFkZGluZyBkYXRhIHRvIHJlcXVlc3QuXG5cdCAqXG5cdCAqIEByZXR1cm5zIHtGdW5jdGlvbn0gSGFuZGxlciBmdW5jdGlvbi5cblx0ICovXG5cdGZ1bmN0aW9uIHNlbmRpbmcoIGR6LCBkYXRhICkge1xuXG5cdFx0cmV0dXJuIGZ1bmN0aW9uKCBmaWxlLCB4aHIsIGZvcm1EYXRhICkge1xuXG5cdFx0XHQvKlxuXHRcdFx0ICogV2Ugc2hvdWxkIG5vdCBhbGxvdyBzZW5kaW5nIGEgZmlsZSwgdGhhdCBleGNlZWRzIHNlcnZlciBwb3N0X21heF9zaXplLlxuXHRcdFx0ICogV2l0aCB0aGlzIFwiaGFja1wiIHdlIHJlZGVmaW5lIHRoZSBkZWZhdWx0IHNlbmQgZnVuY3Rpb25hbGl0eVxuXHRcdFx0ICogdG8gcHJldmVudCBvbmx5IHRoaXMgb2JqZWN0IGZyb20gc2VuZGluZyBhIHJlcXVlc3QgYXQgYWxsLlxuXHRcdFx0ICogVGhlIGZpbGUgdGhhdCBnZW5lcmF0ZWQgdGhhdCBlcnJvciBzaG91bGQgYmUgbWFya2VkIGFzIHJlamVjdGVkLFxuXHRcdFx0ICogc28gRHJvcHpvbmUgd2lsbCBzaWxlbnRseSBpZ25vcmUgaXQuXG5cdFx0XHQgKlxuXHRcdFx0ICogSWYgQ2h1bmtzIGFyZSBlbmFibGVkIHRoZSBmaWxlIHNpemUgd2lsbCBuZXZlciBleGNlZWQgKGJ5IGEgUEhQIGNvbnN0cmFpbnQpIHRoZVxuXHRcdFx0ICogcG9zdE1heFNpemUuIFRoaXMgYmxvY2sgc2hvdWxkbid0IGJlIHJlbW92ZWQgbm9uZXRoZWxlc3MgdW50aWwgdGhlIFwibW9kZXJuXCIgdXBsb2FkIGlzIGNvbXBsZXRlbHlcblx0XHRcdCAqIGRlcHJlY2F0ZWQgYW5kIHJlbW92ZWQuXG5cdFx0XHQgKi9cblx0XHRcdGlmICggZmlsZS5zaXplID4gdGhpcy5kYXRhVHJhbnNmZXIucG9zdE1heFNpemUgKSB7XG5cdFx0XHRcdHhoci5zZW5kID0gZnVuY3Rpb24oKSB7fTtcblxuXHRcdFx0XHRmaWxlLmFjY2VwdGVkID0gZmFsc2U7XG5cdFx0XHRcdGZpbGUucHJvY2Vzc2luZyA9IGZhbHNlO1xuXHRcdFx0XHRmaWxlLnN0YXR1cyA9ICdyZWplY3RlZCc7XG5cdFx0XHRcdGZpbGUucHJldmlld0VsZW1lbnQuY2xhc3NMaXN0LmFkZCggJ2R6LWVycm9yJyApO1xuXHRcdFx0XHRmaWxlLnByZXZpZXdFbGVtZW50LmNsYXNzTGlzdC5hZGQoICdkei1jb21wbGV0ZScgKTtcblxuXHRcdFx0XHRyZXR1cm47XG5cdFx0XHR9XG5cblx0XHRcdE9iamVjdC5rZXlzKCBkYXRhICkuZm9yRWFjaCggZnVuY3Rpb24oIGtleSApIHtcblx0XHRcdFx0Zm9ybURhdGEuYXBwZW5kKCBrZXksIGRhdGFba2V5XSApO1xuXHRcdFx0fSApO1xuXHRcdH07XG5cdH1cblxuXHQvKipcblx0ICogQ29udmVydCBmaWxlcyB0byBpbnB1dCB2YWx1ZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqIEBzaW5jZSAxLjcuMSBBZGRlZCB0aGUgZHogYXJndW1lbnQuXG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBmaWxlcyBGaWxlcyBsaXN0LlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogRHJvcHpvbmUgb2JqZWN0LlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7c3RyaW5nfSBDb252ZXJ0ZWQgdmFsdWUuXG5cdCAqL1xuXHRmdW5jdGlvbiBjb252ZXJ0RmlsZXNUb1ZhbHVlKCBmaWxlcywgZHogKSB7XG5cblx0XHRpZiAoICEgc3VibWl0dGVkVmFsdWVzWyBkei5kYXRhVHJhbnNmZXIuZm9ybUlkIF0gfHwgISBzdWJtaXR0ZWRWYWx1ZXNbIGR6LmRhdGFUcmFuc2Zlci5mb3JtSWQgXVsgZHouZGF0YVRyYW5zZmVyLmZpZWxkSWQgXSApIHtcblx0XHRcdHJldHVybiBmaWxlcy5sZW5ndGggPyBKU09OLnN0cmluZ2lmeSggZmlsZXMgKSA6ICcnO1xuXHRcdH1cblxuXHRcdGZpbGVzLnB1c2guYXBwbHkoIGZpbGVzLCBzdWJtaXR0ZWRWYWx1ZXNbIGR6LmRhdGFUcmFuc2Zlci5mb3JtSWQgXVsgZHouZGF0YVRyYW5zZmVyLmZpZWxkSWQgXSApO1xuXG5cdFx0cmV0dXJuIEpTT04uc3RyaW5naWZ5KCBmaWxlcyApO1xuXHR9XG5cblx0LyoqXG5cdCAqIEdldCBpbnB1dCBlbGVtZW50LlxuXHQgKlxuXHQgKiBAc2luY2UgMS43LjFcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGR6IERyb3B6b25lIG9iamVjdC5cblx0ICpcblx0ICogQHJldHVybnMge2pRdWVyeX0gSGlkZGVuIGlucHV0IGVsZW1lbnQuXG5cdCAqL1xuXHRmdW5jdGlvbiBnZXRJbnB1dCggZHogKSB7XG5cblx0XHRyZXR1cm4galF1ZXJ5KCBkei5lbGVtZW50ICkucGFyZW50cyggJy53cGZvcm1zLWZpZWxkLWZpbGUtdXBsb2FkJyApLmZpbmQoICdpbnB1dFtuYW1lPScgKyBkei5kYXRhVHJhbnNmZXIubmFtZSArICddJyApO1xuXHR9XG5cblx0LyoqXG5cdCAqIFVwZGF0ZSB2YWx1ZSBpbiBpbnB1dC5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkeiBEcm9wem9uZSBvYmplY3QuXG5cdCAqL1xuXHRmdW5jdGlvbiB1cGRhdGVJbnB1dFZhbHVlKCBkeiApIHtcblxuXHRcdHZhciAkaW5wdXQgPSBnZXRJbnB1dCggZHogKTtcblxuXHRcdCRpbnB1dC52YWwoIGNvbnZlcnRGaWxlc1RvVmFsdWUoIGdldFZhbHVlKCBkei5maWxlcyApLCBkeiApICkudHJpZ2dlciggJ2lucHV0JyApO1xuXG5cdFx0aWYgKCB0eXBlb2YgalF1ZXJ5LmZuLnZhbGlkICE9PSAndW5kZWZpbmVkJyApIHtcblx0XHRcdCRpbnB1dC52YWxpZCgpO1xuXHRcdH1cblx0fVxuXG5cdC8qKlxuXHQgKiBDb21wbGV0ZSBldmVudCBoaWdoZXIgb3JkZXIgZnVuY3Rpb24uXG5cdCAqXG5cdCAqIEBkZXByZWNhdGVkIDEuNi4yXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjUuNlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogRHJvcHpvbmUgb2JqZWN0LlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7RnVuY3Rpb259IEhhbmRsZXIgZnVuY3Rpb24uXG5cdCAqL1xuXHRmdW5jdGlvbiBjb21wbGV0ZSggZHogKSB7XG5cblx0XHRyZXR1cm4gZnVuY3Rpb24oKSB7XG5cdFx0XHRkei5sb2FkaW5nID0gZHoubG9hZGluZyB8fCAwO1xuXHRcdFx0ZHoubG9hZGluZy0tO1xuXHRcdFx0ZHoubG9hZGluZyA9IE1hdGgubWF4KCBkei5sb2FkaW5nIC0gMSwgMCApO1xuXHRcdFx0dG9nZ2xlU3VibWl0KCBkeiApO1xuXHRcdFx0dXBkYXRlSW5wdXRWYWx1ZSggZHogKTtcblx0XHR9O1xuXHR9XG5cblx0LyoqXG5cdCAqIEFkZCBhbiBlcnJvciBtZXNzYWdlIHRvIHRoZSBjdXJyZW50IGZpbGUuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjYuMlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZmlsZSAgICAgICAgIEZpbGUgb2JqZWN0LlxuXHQgKiBAcGFyYW0ge3N0cmluZ30gZXJyb3JNZXNzYWdlIEVycm9yIG1lc3NhZ2Vcblx0ICovXG5cdGZ1bmN0aW9uIGFkZEVycm9yTWVzc2FnZSggZmlsZSwgZXJyb3JNZXNzYWdlICkge1xuXG5cdFx0aWYgKCBmaWxlLmlzRXJyb3JOb3RVcGxvYWRlZERpc3BsYXllZCApIHtcblx0XHRcdHJldHVybjtcblx0XHR9XG5cblx0XHR2YXIgc3BhbiA9IGRvY3VtZW50LmNyZWF0ZUVsZW1lbnQoICdzcGFuJyApO1xuXHRcdHNwYW4uaW5uZXJUZXh0ID0gZXJyb3JNZXNzYWdlLnRvU3RyaW5nKCk7XG5cdFx0c3Bhbi5zZXRBdHRyaWJ1dGUoICdkYXRhLWR6LWVycm9ybWVzc2FnZScsICcnICk7XG5cblx0XHRmaWxlLnByZXZpZXdFbGVtZW50LnF1ZXJ5U2VsZWN0b3IoICcuZHotZXJyb3ItbWVzc2FnZScgKS5hcHBlbmRDaGlsZCggc3BhbiApO1xuXHR9XG5cblx0LyoqXG5cdCAqIENvbmZpcm0gdGhlIHVwbG9hZCB0byB0aGUgc2VydmVyLlxuXHQgKlxuXHQgKiBUaGUgY29uZmlybWF0aW9uIGlzIG5lZWRlZCBpbiBvcmRlciB0byBsZXQgUEhQIGtub3dcblx0ICogdGhhdCBhbGwgdGhlIGNodW5rcyBoYXZlIGJlZW4gdXBsb2FkZWQuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjYuMlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogRHJvcHpvbmUgb2JqZWN0LlxuXHQgKlxuXHQgKiBAcmV0dXJucyB7RnVuY3Rpb259IEhhbmRsZXIgZnVuY3Rpb24uXG5cdCAqL1xuXHRmdW5jdGlvbiBjb25maXJtQ2h1bmtzRmluaXNoVXBsb2FkKCBkeiApIHtcblxuXHRcdHJldHVybiBmdW5jdGlvbiBjb25maXJtKCBmaWxlICkge1xuXG5cdFx0XHRpZiAoICEgZmlsZS5yZXRyaWVzICkge1xuXHRcdFx0XHRmaWxlLnJldHJpZXMgPSAwO1xuXHRcdFx0fVxuXG5cdFx0XHRpZiAoICdlcnJvcicgPT09IGZpbGUuc3RhdHVzICkge1xuXHRcdFx0XHRyZXR1cm47XG5cdFx0XHR9XG5cblx0XHRcdC8qKlxuXHRcdFx0ICogUmV0cnkgZmluYWxpemUgZnVuY3Rpb24uXG5cdFx0XHQgKlxuXHRcdFx0ICogQHNpbmNlIDEuNi4yXG5cdFx0XHQgKi9cblx0XHRcdGZ1bmN0aW9uIHJldHJ5KCkge1xuXHRcdFx0XHRmaWxlLnJldHJpZXMrKztcblxuXHRcdFx0XHRpZiAoIGZpbGUucmV0cmllcyA9PT0gMyApIHtcblx0XHRcdFx0XHRhZGRFcnJvck1lc3NhZ2UoIGZpbGUsIHdpbmRvdy53cGZvcm1zX2ZpbGVfdXBsb2FkLmVycm9ycy5maWxlX25vdF91cGxvYWRlZCApO1xuXHRcdFx0XHRcdHJldHVybjtcblx0XHRcdFx0fVxuXG5cdFx0XHRcdHNldFRpbWVvdXQoIGZ1bmN0aW9uKCkge1xuXHRcdFx0XHRcdGNvbmZpcm0oIGZpbGUgKTtcblx0XHRcdFx0fSwgNTAwMCAqIGZpbGUucmV0cmllcyApO1xuXHRcdFx0fVxuXG5cdFx0XHQvKipcblx0XHRcdCAqIEZhaWwgaGFuZGxlciBmb3IgYWpheCByZXF1ZXN0LlxuXHRcdFx0ICpcblx0XHRcdCAqIEBzaW5jZSAxLjYuMlxuXHRcdFx0ICpcblx0XHRcdCAqIEBwYXJhbSB7b2JqZWN0fSByZXNwb25zZSBSZXNwb25zZSBmcm9tIHRoZSBzZXJ2ZXJcblx0XHRcdCAqL1xuXHRcdFx0ZnVuY3Rpb24gZmFpbCggcmVzcG9uc2UgKSB7XG5cblx0XHRcdFx0dmFyIGhhc1NwZWNpZmljRXJyb3IgPVx0cmVzcG9uc2UucmVzcG9uc2VKU09OICYmXG5cdFx0XHRcdFx0XHRcdFx0XHRcdHJlc3BvbnNlLnJlc3BvbnNlSlNPTi5zdWNjZXNzID09PSBmYWxzZSAmJlxuXHRcdFx0XHRcdFx0XHRcdFx0XHRyZXNwb25zZS5yZXNwb25zZUpTT04uZGF0YTtcblxuXHRcdFx0XHRpZiAoIGhhc1NwZWNpZmljRXJyb3IgKSB7XG5cdFx0XHRcdFx0YWRkRXJyb3JNZXNzYWdlKCBmaWxlLCByZXNwb25zZS5yZXNwb25zZUpTT04uZGF0YSApO1xuXHRcdFx0XHR9IGVsc2Uge1xuXHRcdFx0XHRcdHJldHJ5KCk7XG5cdFx0XHRcdH1cblx0XHRcdH1cblxuXHRcdFx0LyoqXG5cdFx0XHQgKiBIYW5kbGVyIGZvciBhamF4IHJlcXVlc3QuXG5cdFx0XHQgKlxuXHRcdFx0ICogQHNpbmNlIDEuNi4yXG5cdFx0XHQgKlxuXHRcdFx0ICogQHBhcmFtIHtvYmplY3R9IHJlc3BvbnNlIFJlc3BvbnNlIGZyb20gdGhlIHNlcnZlclxuXHRcdFx0ICovXG5cdFx0XHRmdW5jdGlvbiBjb21wbGV0ZSggcmVzcG9uc2UgKSB7XG5cblx0XHRcdFx0ZmlsZS5jaHVua1Jlc3BvbnNlID0gSlNPTi5zdHJpbmdpZnkoIHsgZGF0YTogcmVzcG9uc2UgfSApO1xuXHRcdFx0XHRkei5sb2FkaW5nID0gZHoubG9hZGluZyB8fCAwO1xuXHRcdFx0XHRkei5sb2FkaW5nLS07XG5cdFx0XHRcdGR6LmxvYWRpbmcgPSBNYXRoLm1heCggZHoubG9hZGluZywgMCApO1xuXG5cdFx0XHRcdHRvZ2dsZVN1Ym1pdCggZHogKTtcblx0XHRcdFx0dXBkYXRlSW5wdXRWYWx1ZSggZHogKTtcblx0XHRcdH1cblxuXHRcdFx0d3AuYWpheC5wb3N0KCBqUXVlcnkuZXh0ZW5kKFxuXHRcdFx0XHR7XG5cdFx0XHRcdFx0YWN0aW9uOiAnd3Bmb3Jtc19maWxlX2NodW5rc191cGxvYWRlZCcsXG5cdFx0XHRcdFx0Zm9ybV9pZDogZHouZGF0YVRyYW5zZmVyLmZvcm1JZCxcblx0XHRcdFx0XHRmaWVsZF9pZDogZHouZGF0YVRyYW5zZmVyLmZpZWxkSWQsXG5cdFx0XHRcdFx0bmFtZTogZmlsZS5uYW1lLFxuXHRcdFx0XHR9LFxuXHRcdFx0XHRkei5vcHRpb25zLnBhcmFtcy5jYWxsKCBkeiwgbnVsbCwgbnVsbCwge2ZpbGU6IGZpbGUsIGluZGV4OiAwfSApXG5cdFx0XHQpICkudGhlbiggY29tcGxldGUgKS5mYWlsKCBmYWlsICk7XG5cblx0XHRcdC8vIE1vdmUgdG8gdXBsb2FkIHRoZSBuZXh0IGZpbGUsIGlmIGFueS5cblx0XHRcdGR6LnByb2Nlc3NRdWV1ZSgpO1xuXHRcdH07XG5cdH1cblxuXHQvKipcblx0ICogVG9nZ2xlIHNob3dpbmcgZW1wdHkgbWVzc2FnZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkeiBEcm9wem9uZSBvYmplY3QuXG5cdCAqL1xuXHRmdW5jdGlvbiB0b2dnbGVNZXNzYWdlKCBkeiApIHtcblxuXHRcdHNldFRpbWVvdXQoIGZ1bmN0aW9uKCkge1xuXHRcdFx0dmFyIHZhbGlkRmlsZXMgPSBkei5maWxlcy5maWx0ZXIoIGZ1bmN0aW9uKCBmaWxlICkge1xuXHRcdFx0XHRyZXR1cm4gZmlsZS5hY2NlcHRlZDtcblx0XHRcdH0gKTtcblxuXHRcdFx0aWYgKCB2YWxpZEZpbGVzLmxlbmd0aCA+PSBkei5vcHRpb25zLm1heEZpbGVzICkge1xuXHRcdFx0XHRkei5lbGVtZW50LnF1ZXJ5U2VsZWN0b3IoICcuZHotbWVzc2FnZScgKS5jbGFzc0xpc3QuYWRkKCAnaGlkZScgKTtcblx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdGR6LmVsZW1lbnQucXVlcnlTZWxlY3RvciggJy5kei1tZXNzYWdlJyApLmNsYXNzTGlzdC5yZW1vdmUoICdoaWRlJyApO1xuXHRcdFx0fVxuXHRcdH0sIDAgKTtcblx0fVxuXG5cdC8qKlxuXHQgKiBUb2dnbGUgZXJyb3IgbWVzc2FnZSBpZiB0b3RhbCBzaXplIG1vcmUgdGhhbiBsaW1pdC5cblx0ICogUnVucyBmb3IgZWFjaCBmaWxlLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGZpbGUgQ3VycmVudCBmaWxlLlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogICBEcm9wem9uZSBvYmplY3QuXG5cdCAqL1xuXHRmdW5jdGlvbiB2YWxpZGF0ZVBvc3RNYXhTaXplRXJyb3IoIGZpbGUsIGR6ICkge1xuXG5cdFx0c2V0VGltZW91dCggZnVuY3Rpb24oKSB7XG5cdFx0XHRpZiAoIGZpbGUuc2l6ZSA+PSBkei5kYXRhVHJhbnNmZXIucG9zdE1heFNpemUgKSB7XG5cdFx0XHRcdHZhciBlcnJvck1lc3NhZ2UgPSB3aW5kb3cud3Bmb3Jtc19maWxlX3VwbG9hZC5lcnJvcnMucG9zdF9tYXhfc2l6ZTtcblx0XHRcdFx0aWYgKCAhIGZpbGUuaXNFcnJvck5vdFVwbG9hZGVkRGlzcGxheWVkICkge1xuXHRcdFx0XHRcdGZpbGUuaXNFcnJvck5vdFVwbG9hZGVkRGlzcGxheWVkID0gdHJ1ZTtcblx0XHRcdFx0XHRlcnJvck1lc3NhZ2UgPSB3aW5kb3cud3Bmb3Jtc19maWxlX3VwbG9hZC5lcnJvcnMuZmlsZV9ub3RfdXBsb2FkZWQgKyAnICcgKyBlcnJvck1lc3NhZ2U7XG5cdFx0XHRcdFx0YWRkRXJyb3JNZXNzYWdlKCBmaWxlLCBlcnJvck1lc3NhZ2UgKTtcblx0XHRcdFx0fVxuXHRcdFx0fVxuXHRcdH0sIDEgKTtcblx0fVxuXG5cdC8qKlxuXHQgKiBTdGFydCBGaWxlIFVwbG9hZC5cblx0ICpcblx0ICogVGhpcyB3b3VsZCBkbyB0aGUgaW5pdGlhbCByZXF1ZXN0IHRvIHN0YXJ0IGEgZmlsZSB1cGxvYWQuIE5vIGNodW5rXG5cdCAqIGlzIHVwbG9hZGVkIGF0IHRoaXMgc3RhZ2UsIGluc3RlYWQgYWxsIHRoZSBpbmZvcm1hdGlvbiByZWxhdGVkIHRvIHRoZVxuXHQgKiBmaWxlIGFyZSBzZW5kIHRvIHRoZSBzZXJ2ZXIgd2FpdGluZyBmb3IgYW4gYXV0aG9yaXphdGlvbi5cblx0ICpcblx0ICogSWYgdGhlIHNlcnZlciBhdXRob3JpemVzIHRoZSBjbGllbnQgd291bGQgc3RhcnQgdXBsb2FkaW5nIHRoZSBjaHVua3MuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjYuMlxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogICBEcm9wem9uZSBvYmplY3QuXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBmaWxlIEN1cnJlbnQgZmlsZS5cblx0ICovXG5cdGZ1bmN0aW9uIGluaXRGaWxlVXBsb2FkKCBkeiwgZmlsZSApIHtcblxuXHRcdHdwLmFqYXgucG9zdCggalF1ZXJ5LmV4dGVuZChcblx0XHRcdHtcblx0XHRcdFx0YWN0aW9uIDogJ3dwZm9ybXNfdXBsb2FkX2NodW5rX2luaXQnLFxuXHRcdFx0XHRmb3JtX2lkOiBkei5kYXRhVHJhbnNmZXIuZm9ybUlkLFxuXHRcdFx0XHRmaWVsZF9pZDogZHouZGF0YVRyYW5zZmVyLmZpZWxkSWQsXG5cdFx0XHRcdG5hbWU6IGZpbGUubmFtZSxcblx0XHRcdFx0c2xvdzogaXNTbG93LFxuXHRcdFx0fSxcblx0XHRcdGR6Lm9wdGlvbnMucGFyYW1zLmNhbGwoIGR6LCBudWxsLCBudWxsLCB7ZmlsZTogZmlsZSwgaW5kZXg6IDB9IClcblx0XHQpICkudGhlbiggZnVuY3Rpb24oIHJlc3BvbnNlICkge1xuXG5cdFx0XHQvLyBGaWxlIHVwbG9hZCBoYXMgYmVlbiBhdXRob3JpemVkLlxuXG5cdFx0XHRmb3IgKCB2YXIga2V5IGluIHJlc3BvbnNlICkge1xuXHRcdFx0XHRkei5vcHRpb25zWyBrZXkgXSA9IHJlc3BvbnNlWyBrZXkgXTtcblx0XHRcdH1cblxuXHRcdFx0aWYgKCByZXNwb25zZS5kemNodW5rc2l6ZSApIHtcblx0XHRcdFx0ZHoub3B0aW9ucy5jaHVua1NpemUgPSBwYXJzZUludCggcmVzcG9uc2UuZHpjaHVua3NpemUsIDEwICk7XG5cdFx0XHRcdGZpbGUudXBsb2FkLnRvdGFsQ2h1bmtDb3VudCA9IE1hdGguY2VpbCggZmlsZS5zaXplIC8gZHoub3B0aW9ucy5jaHVua1NpemUgKTtcblx0XHRcdH1cblxuXHRcdFx0ZHoucHJvY2Vzc1F1ZXVlKCk7XG5cdFx0fSApLmZhaWwoIGZ1bmN0aW9uKCByZXNwb25zZSApIHtcblxuXHRcdFx0ZmlsZS5zdGF0dXMgPSAnZXJyb3InO1xuXG5cdFx0XHRpZiAoICEgZmlsZS54aHIgKSB7XG5cdFx0XHRcdGNvbnN0IGZpZWxkID0gZHouZWxlbWVudC5jbG9zZXN0KCAnLndwZm9ybXMtZmllbGQnICk7XG5cdFx0XHRcdGNvbnN0IGhpZGRlbklucHV0ID0gZmllbGQucXVlcnlTZWxlY3RvciggJy5kcm9wem9uZS1pbnB1dCcgKTtcblx0XHRcdFx0Y29uc3QgZXJyb3JNZXNzYWdlID0gd2luZG93LndwZm9ybXNfZmlsZV91cGxvYWQuZXJyb3JzLmZpbGVfbm90X3VwbG9hZGVkICsgJyAnICsgd2luZG93LndwZm9ybXNfZmlsZV91cGxvYWQuZXJyb3JzLmRlZmF1bHRfZXJyb3I7XG5cblx0XHRcdFx0ZmlsZS5wcmV2aWV3RWxlbWVudC5jbGFzc0xpc3QuYWRkKCAnZHotcHJvY2Vzc2luZycsICdkei1lcnJvcicsICdkei1jb21wbGV0ZScgKTtcblx0XHRcdFx0aGlkZGVuSW5wdXQuY2xhc3NMaXN0LmFkZCggJ3dwZm9ybXMtZXJyb3InICk7XG5cdFx0XHRcdGZpZWxkLmNsYXNzTGlzdC5hZGQoICd3cGZvcm1zLWhhcy1lcnJvcicgKTtcblx0XHRcdFx0YWRkRXJyb3JNZXNzYWdlKCBmaWxlLCBlcnJvck1lc3NhZ2UgKTtcblx0XHRcdH1cblxuXHRcdFx0ZHoucHJvY2Vzc1F1ZXVlKCk7XG5cdFx0fSApO1xuXHR9XG5cblx0LyoqXG5cdCAqIFZhbGlkYXRlIHRoZSBmaWxlIHdoZW4gaXQgd2FzIGFkZGVkIGluIHRoZSBkcm9wem9uZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSBkeiBEcm9wem9uZSBvYmplY3QuXG5cdCAqXG5cdCAqIEByZXR1cm5zIHtGdW5jdGlvbn0gSGFuZGxlciBmdW5jdGlvbi5cblx0ICovXG5cdGZ1bmN0aW9uIGFkZGVkRmlsZSggZHogKSB7XG5cblx0XHRyZXR1cm4gZnVuY3Rpb24oIGZpbGUgKSB7XG5cblx0XHRcdGlmICggZmlsZS5zaXplID49IGR6LmRhdGFUcmFuc2Zlci5wb3N0TWF4U2l6ZSApIHtcblx0XHRcdFx0dmFsaWRhdGVQb3N0TWF4U2l6ZUVycm9yKCBmaWxlLCBkeiApO1xuXHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0c3BlZWRUZXN0KCBmdW5jdGlvbigpIHtcblx0XHRcdFx0XHRpbml0RmlsZVVwbG9hZCggZHosIGZpbGUgKTtcblx0XHRcdFx0fSApO1xuXHRcdFx0fVxuXG5cdFx0XHRkei5sb2FkaW5nID0gZHoubG9hZGluZyB8fCAwO1xuXHRcdFx0ZHoubG9hZGluZysrO1xuXHRcdFx0dG9nZ2xlU3VibWl0KCBkeiApO1xuXG5cdFx0XHR0b2dnbGVNZXNzYWdlKCBkeiApO1xuXHRcdH07XG5cdH1cblxuXHQvKipcblx0ICogU2VuZCBhbiBBSkFYIHJlcXVlc3QgdG8gcmVtb3ZlIGZpbGUgZnJvbSB0aGUgc2VydmVyLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtzdHJpbmd9IGZpbGUgRmlsZSBuYW1lLlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogRHJvcHpvbmUgb2JqZWN0LlxuXHQgKi9cblx0ZnVuY3Rpb24gcmVtb3ZlRnJvbVNlcnZlciggZmlsZSwgZHogKSB7XG5cblx0XHR3cC5hamF4LnBvc3QoIHtcblx0XHRcdGFjdGlvbjogJ3dwZm9ybXNfcmVtb3ZlX2ZpbGUnLFxuXHRcdFx0ZmlsZTogZmlsZSxcblx0XHRcdGZvcm1faWQ6IGR6LmRhdGFUcmFuc2Zlci5mb3JtSWQsXG5cdFx0XHRmaWVsZF9pZDogZHouZGF0YVRyYW5zZmVyLmZpZWxkSWQsXG5cdFx0fSApO1xuXHR9XG5cblx0LyoqXG5cdCAqIEluaXQgdGhlIGZpbGUgcmVtb3ZhbCBvbiBzZXJ2ZXIgd2hlbiB1c2VyIHJlbW92ZWQgaXQgb24gZnJvbnQtZW5kLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGR6IERyb3B6b25lIG9iamVjdC5cblx0ICpcblx0ICogQHJldHVybnMge0Z1bmN0aW9ufSBIYW5kbGVyIGZ1bmN0aW9uLlxuXHQgKi9cblx0ZnVuY3Rpb24gcmVtb3ZlZEZpbGUoIGR6ICkge1xuXG5cdFx0cmV0dXJuIGZ1bmN0aW9uKCBmaWxlICkge1xuXHRcdFx0dG9nZ2xlTWVzc2FnZSggZHogKTtcblxuXHRcdFx0dmFyIGpzb24gPSBmaWxlLmNodW5rUmVzcG9uc2UgfHwgKCBmaWxlLnhociB8fCB7fSApLnJlc3BvbnNlVGV4dDtcblxuXHRcdFx0aWYgKCBqc29uICkge1xuXHRcdFx0XHR2YXIgb2JqZWN0ID0gcGFyc2VKU09OKCBqc29uICk7XG5cblx0XHRcdFx0aWYgKCBvYmplY3QgJiYgb2JqZWN0LmRhdGEgJiYgb2JqZWN0LmRhdGEuZmlsZSApIHtcblx0XHRcdFx0XHRyZW1vdmVGcm9tU2VydmVyKCBvYmplY3QuZGF0YS5maWxlLCBkeiApO1xuXHRcdFx0XHR9XG5cdFx0XHR9XG5cblx0XHRcdC8vIFJlbW92ZSBzdWJtaXR0ZWQgdmFsdWUuXG5cdFx0XHRpZiAoIE9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbCggZmlsZSwgJ2lzRGVmYXVsdCcgKSAmJiBmaWxlLmlzRGVmYXVsdCApIHtcblx0XHRcdFx0c3VibWl0dGVkVmFsdWVzWyBkei5kYXRhVHJhbnNmZXIuZm9ybUlkIF1bIGR6LmRhdGFUcmFuc2Zlci5maWVsZElkIF0uc3BsaWNlKCBmaWxlLmluZGV4LCAxICk7XG5cdFx0XHRcdGR6Lm9wdGlvbnMubWF4RmlsZXMrKztcblx0XHRcdFx0cmVtb3ZlRnJvbVNlcnZlciggZmlsZS5maWxlLCBkeiApO1xuXHRcdFx0fVxuXG5cdFx0XHR1cGRhdGVJbnB1dFZhbHVlKCBkeiApO1xuXG5cdFx0XHRkei5sb2FkaW5nID0gZHoubG9hZGluZyB8fCAwO1xuXHRcdFx0ZHoubG9hZGluZy0tO1xuXHRcdFx0ZHoubG9hZGluZyA9IE1hdGgubWF4KCBkei5sb2FkaW5nLCAwICk7XG5cblx0XHRcdHRvZ2dsZVN1Ym1pdCggZHogKTtcblxuXHRcdFx0Y29uc3QgbnVtRXJyb3JzID0gZHouZWxlbWVudC5xdWVyeVNlbGVjdG9yQWxsKCAnLmR6LXByZXZpZXcuZHotZXJyb3InICkubGVuZ3RoO1xuXG5cdFx0XHRpZiAoIG51bUVycm9ycyA9PT0gMCApIHtcblx0XHRcdFx0ZHouZWxlbWVudC5jbGFzc0xpc3QucmVtb3ZlKCAnd3Bmb3Jtcy1lcnJvcicgKTtcblx0XHRcdFx0ZHouZWxlbWVudC5jbG9zZXN0KCAnLndwZm9ybXMtZmllbGQnICkuY2xhc3NMaXN0LnJlbW92ZSggJ3dwZm9ybXMtaGFzLWVycm9yJyApO1xuXHRcdFx0fVxuXHRcdH07XG5cdH1cblxuXHQvKipcblx0ICogUHJvY2VzcyBhbnkgZXJyb3IgdGhhdCB3YXMgZmlyZWQgcGVyIGVhY2ggZmlsZS5cblx0ICogVGhlcmUgbWlnaHQgYmUgc2V2ZXJhbCBlcnJvcnMgcGVyIGZpbGUsIGluIHRoYXQgY2FzZSAtIGRpc3BsYXkgXCJub3QgdXBsb2FkZWRcIiB0ZXh0IG9ubHkgb25jZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42LjFcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGR6IERyb3B6b25lIG9iamVjdC5cblx0ICpcblx0ICogQHJldHVybnMge0Z1bmN0aW9ufSBIYW5kbGVyIGZ1bmN0aW9uLlxuXHQgKi9cblx0ZnVuY3Rpb24gZXJyb3IoIGR6ICkge1xuXG5cdFx0cmV0dXJuIGZ1bmN0aW9uKCBmaWxlLCBlcnJvck1lc3NhZ2UgKSB7XG5cblx0XHRcdGlmICggZmlsZS5pc0Vycm9yTm90VXBsb2FkZWREaXNwbGF5ZWQgKSB7XG5cdFx0XHRcdHJldHVybjtcblx0XHRcdH1cblxuXHRcdFx0aWYgKCB0eXBlb2YgZXJyb3JNZXNzYWdlID09PSAnb2JqZWN0JyApIHtcblx0XHRcdFx0ZXJyb3JNZXNzYWdlID0gT2JqZWN0LnByb3RvdHlwZS5oYXNPd25Qcm9wZXJ0eS5jYWxsKCBlcnJvck1lc3NhZ2UsICdkYXRhJyApICYmIHR5cGVvZiBlcnJvck1lc3NhZ2UuZGF0YSA9PT0gJ3N0cmluZycgPyBlcnJvck1lc3NhZ2UuZGF0YSA6ICcnO1xuXHRcdFx0fVxuXG5cdFx0XHRlcnJvck1lc3NhZ2UgPSBlcnJvck1lc3NhZ2UgIT09ICcwJyA/IGVycm9yTWVzc2FnZSA6ICcnO1xuXG5cdFx0XHRmaWxlLmlzRXJyb3JOb3RVcGxvYWRlZERpc3BsYXllZCA9IHRydWU7XG5cdFx0XHRmaWxlLnByZXZpZXdFbGVtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoICdbZGF0YS1kei1lcnJvcm1lc3NhZ2VdJyApWzBdLnRleHRDb250ZW50ID0gd2luZG93LndwZm9ybXNfZmlsZV91cGxvYWQuZXJyb3JzLmZpbGVfbm90X3VwbG9hZGVkICsgJyAnICsgZXJyb3JNZXNzYWdlO1xuXHRcdFx0ZHouZWxlbWVudC5jbGFzc0xpc3QuYWRkKCAnd3Bmb3Jtcy1lcnJvcicgKTtcblx0XHRcdGR6LmVsZW1lbnQuY2xvc2VzdCggJy53cGZvcm1zLWZpZWxkJyApLmNsYXNzTGlzdC5hZGQoICd3cGZvcm1zLWhhcy1lcnJvcicgKTtcblx0XHR9O1xuXHR9XG5cblx0LyoqXG5cdCAqIFByZXNldCBwcmV2aW91c2x5IHN1Ym1pdHRlZCBmaWxlcyB0byB0aGUgZHJvcHpvbmUuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjcuMVxuXHQgKlxuXHQgKiBAcGFyYW0ge29iamVjdH0gZHogRHJvcHpvbmUgb2JqZWN0LlxuXHQgKi9cblx0ZnVuY3Rpb24gcHJlc2V0U3VibWl0dGVkRGF0YSggZHogKSB7XG5cblx0XHR2YXIgZmlsZXMgPSBwYXJzZUpTT04oIGdldElucHV0KCBkeiApLnZhbCgpICk7XG5cblx0XHRpZiAoICEgZmlsZXMgfHwgISBmaWxlcy5sZW5ndGggKSB7XG5cdFx0XHRyZXR1cm47XG5cdFx0fVxuXG5cdFx0c3VibWl0dGVkVmFsdWVzW2R6LmRhdGFUcmFuc2Zlci5mb3JtSWRdID0gW107XG5cblx0XHQvLyBXZSBkbyBkZWVwIGNsb25pbmcgYW4gb2JqZWN0IHRvIGJlIHN1cmUgdGhhdCBkYXRhIGlzIHBhc3NlZCB3aXRob3V0IGxpbmtzLlxuXHRcdHN1Ym1pdHRlZFZhbHVlc1tkei5kYXRhVHJhbnNmZXIuZm9ybUlkXVtkei5kYXRhVHJhbnNmZXIuZmllbGRJZF0gPSBKU09OLnBhcnNlKCBKU09OLnN0cmluZ2lmeSggZmlsZXMgKSApO1xuXG5cdFx0ZmlsZXMuZm9yRWFjaCggZnVuY3Rpb24oIGZpbGUsIGluZGV4ICkge1xuXG5cdFx0XHRmaWxlLmlzRGVmYXVsdCA9IHRydWU7XG5cdFx0XHRmaWxlLmluZGV4ID0gaW5kZXg7XG5cblx0XHRcdGlmICggZmlsZS50eXBlLm1hdGNoKCAvaW1hZ2UuKi8gKSApIHtcblx0XHRcdFx0ZHouZGlzcGxheUV4aXN0aW5nRmlsZSggZmlsZSwgZmlsZS51cmwgKTtcblxuXHRcdFx0XHRyZXR1cm47XG5cdFx0XHR9XG5cblx0XHRcdGR6LmVtaXQoICdhZGRlZGZpbGUnLCBmaWxlICk7XG5cdFx0XHRkei5lbWl0KCAnY29tcGxldGUnLCBmaWxlICk7XG5cdFx0fSApO1xuXG5cdFx0ZHoub3B0aW9ucy5tYXhGaWxlcyA9IGR6Lm9wdGlvbnMubWF4RmlsZXMgLSBmaWxlcy5sZW5ndGg7XG5cdH1cblxuXHQvKipcblx0ICogRHJvcHpvbmUuanMgaW5pdCBmb3IgZWFjaCBmaWVsZC5cblx0ICpcblx0ICogQHNpbmNlIDEuNS42XG5cdCAqXG5cdCAqIEBwYXJhbSB7b2JqZWN0fSAkZWwgV1BGb3JtcyB1cGxvYWRlciBET00gZWxlbWVudC5cblx0ICpcblx0ICogQHJldHVybnMge29iamVjdH0gRHJvcHpvbmUgb2JqZWN0LlxuXHQgKi9cblx0ZnVuY3Rpb24gZHJvcFpvbmVJbml0KCAkZWwgKSB7XG5cblx0XHRpZiAoICRlbC5kcm9wem9uZSApIHtcblx0XHRcdHJldHVybiAkZWwuZHJvcHpvbmU7XG5cdFx0fVxuXG5cdFx0dmFyIGZvcm1JZCA9IHBhcnNlSW50KCAkZWwuZGF0YXNldC5mb3JtSWQsIDEwICk7XG5cdFx0dmFyIGZpZWxkSWQgPSBwYXJzZUludCggJGVsLmRhdGFzZXQuZmllbGRJZCwgMTAgKSB8fCAwO1xuXHRcdHZhciBtYXhGaWxlcyA9IHBhcnNlSW50KCAkZWwuZGF0YXNldC5tYXhGaWxlTnVtYmVyLCAxMCApO1xuXG5cdFx0dmFyIGFjY2VwdGVkRmlsZXMgPSAkZWwuZGF0YXNldC5leHRlbnNpb25zLnNwbGl0KCAnLCcgKS5tYXAoIGZ1bmN0aW9uKCBlbCApIHtcblx0XHRcdHJldHVybiAnLicgKyBlbDtcblx0XHR9ICkuam9pbiggJywnICk7XG5cblx0XHQvLyBDb25maWd1cmUgYW5kIG1vZGlmeSBEcm9wem9uZSBsaWJyYXJ5LlxuXHRcdHZhciBkeiA9IG5ldyB3aW5kb3cuRHJvcHpvbmUoICRlbCwge1xuXHRcdFx0dXJsOiB3aW5kb3cud3Bmb3Jtc19maWxlX3VwbG9hZC51cmwsXG5cdFx0XHRhZGRSZW1vdmVMaW5rczogdHJ1ZSxcblx0XHRcdGNodW5raW5nOiB0cnVlLFxuXHRcdFx0Zm9yY2VDaHVua2luZzogdHJ1ZSxcblx0XHRcdHJldHJ5Q2h1bmtzOiB0cnVlLFxuXHRcdFx0Y2h1bmtTaXplOiBwYXJzZUludCggJGVsLmRhdGFzZXQuZmlsZUNodW5rU2l6ZSwgMTAgKSxcblx0XHRcdHBhcmFtTmFtZTogJGVsLmRhdGFzZXQuaW5wdXROYW1lLFxuXHRcdFx0cGFyYWxsZWxDaHVua1VwbG9hZHM6ICEhICggJGVsLmRhdGFzZXQucGFyYWxsZWxVcGxvYWRzIHx8ICcnICkubWF0Y2goIC9edHJ1ZSQvaSApLFxuXHRcdFx0cGFyYWxsZWxVcGxvYWRzOiBwYXJzZUludCggJGVsLmRhdGFzZXQubWF4UGFyYWxsZWxVcGxvYWRzLCAxMCApLFxuXHRcdFx0YXV0b1Byb2Nlc3NRdWV1ZTogZmFsc2UsXG5cdFx0XHRtYXhGaWxlc2l6ZTogKCBwYXJzZUludCggJGVsLmRhdGFzZXQubWF4U2l6ZSwgMTAgKSAvICggMTAyNCAqIDEwMjQgKSApLnRvRml4ZWQoIDIgKSxcblx0XHRcdG1heEZpbGVzOiBtYXhGaWxlcyxcblx0XHRcdGFjY2VwdGVkRmlsZXM6IGFjY2VwdGVkRmlsZXMsXG5cdFx0XHRkaWN0TWF4RmlsZXNFeGNlZWRlZDogd2luZG93LndwZm9ybXNfZmlsZV91cGxvYWQuZXJyb3JzLmZpbGVfbGltaXQucmVwbGFjZSggJ3tmaWxlTGltaXR9JywgbWF4RmlsZXMgKSxcblx0XHRcdGRpY3RJbnZhbGlkRmlsZVR5cGU6IHdpbmRvdy53cGZvcm1zX2ZpbGVfdXBsb2FkLmVycm9ycy5maWxlX2V4dGVuc2lvbixcblx0XHRcdGRpY3RGaWxlVG9vQmlnOiB3aW5kb3cud3Bmb3Jtc19maWxlX3VwbG9hZC5lcnJvcnMuZmlsZV9zaXplLFxuXHRcdH0gKTtcblxuXHRcdC8vIEN1c3RvbSB2YXJpYWJsZXMuXG5cdFx0ZHouZGF0YVRyYW5zZmVyID0ge1xuXHRcdFx0cG9zdE1heFNpemU6ICRlbC5kYXRhc2V0Lm1heFNpemUsXG5cdFx0XHRuYW1lOiAkZWwuZGF0YXNldC5pbnB1dE5hbWUsXG5cdFx0XHRmb3JtSWQ6IGZvcm1JZCxcblx0XHRcdGZpZWxkSWQ6IGZpZWxkSWQsXG5cdFx0fTtcblxuXHRcdHByZXNldFN1Ym1pdHRlZERhdGEoIGR6ICk7XG5cblx0XHQvLyBQcm9jZXNzIGV2ZW50cy5cblx0XHRkei5vbiggJ3NlbmRpbmcnLCBzZW5kaW5nKCBkeiwge1xuXHRcdFx0YWN0aW9uOiAnd3Bmb3Jtc191cGxvYWRfY2h1bmsnLFxuXHRcdFx0Zm9ybV9pZDogZm9ybUlkLFxuXHRcdFx0ZmllbGRfaWQ6IGZpZWxkSWQsXG5cdFx0fSApICk7XG5cdFx0ZHoub24oICdhZGRlZGZpbGUnLCBhZGRlZEZpbGUoIGR6ICkgKTtcblx0XHRkei5vbiggJ3JlbW92ZWRmaWxlJywgcmVtb3ZlZEZpbGUoIGR6ICkgKTtcblx0XHRkei5vbiggJ2NvbXBsZXRlJywgY29uZmlybUNodW5rc0ZpbmlzaFVwbG9hZCggZHogKSApO1xuXHRcdGR6Lm9uKCAnZXJyb3InLCBlcnJvciggZHogKSApO1xuXG5cdFx0cmV0dXJuIGR6O1xuXHR9XG5cblx0LyoqXG5cdCAqIEhpZGRlbiBEcm9wem9uZSBpbnB1dCBmb2N1cyBldmVudCBoYW5kbGVyLlxuXHQgKlxuXHQgKiBAc2luY2UgMS44LjFcblx0ICovXG5cdGZ1bmN0aW9uIGRyb3B6b25lSW5wdXRGb2N1cygpIHtcblxuXHRcdCQoIHRoaXMgKS5wcmV2KCAnLndwZm9ybXMtdXBsb2FkZXInICkuYWRkQ2xhc3MoICd3cGZvcm1zLWZvY3VzJyApO1xuXHR9XG5cblx0LyoqXG5cdCAqIEhpZGRlbiBEcm9wem9uZSBpbnB1dCBibHVyIGV2ZW50IGhhbmRsZXIuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjguMVxuXHQgKi9cblx0ZnVuY3Rpb24gZHJvcHpvbmVJbnB1dEJsdXIoKSB7XG5cblx0XHQkKCB0aGlzICkucHJldiggJy53cGZvcm1zLXVwbG9hZGVyJyApLnJlbW92ZUNsYXNzKCAnd3Bmb3Jtcy1mb2N1cycgKTtcblx0fVxuXG5cdC8qKlxuXHQgKiBIaWRkZW4gRHJvcHpvbmUgaW5wdXQgYmx1ciBldmVudCBoYW5kbGVyLlxuXHQgKlxuXHQgKiBAc2luY2UgMS44LjFcblx0ICpcblx0ICogQHBhcmFtIHtvYmplY3R9IGUgRXZlbnQgb2JqZWN0LlxuXHQgKi9cblx0ZnVuY3Rpb24gZHJvcHpvbmVJbnB1dEtleXByZXNzKCBlICkge1xuXG5cdFx0ZS5wcmV2ZW50RGVmYXVsdCgpO1xuXG5cdFx0aWYgKCBlLmtleUNvZGUgIT09IDEzICkge1xuXHRcdFx0cmV0dXJuO1xuXHRcdH1cblxuXHRcdCQoIHRoaXMgKS5wcmV2KCAnLndwZm9ybXMtdXBsb2FkZXInICkudHJpZ2dlciggJ2NsaWNrJyApO1xuXHR9XG5cblx0LyoqXG5cdCAqIEhpZGRlbiBEcm9wem9uZSBpbnB1dCBibHVyIGV2ZW50IGhhbmRsZXIuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjguMVxuXHQgKi9cblx0ZnVuY3Rpb24gZHJvcHpvbmVDbGljaygpIHtcblxuXHRcdCQoIHRoaXMgKS5uZXh0KCAnLmRyb3B6b25lLWlucHV0JyApLnRyaWdnZXIoICdmb2N1cycgKTtcblx0fVxuXG5cdC8qKlxuXHQgKiBDbGFzc2ljIEZpbGUgdXBsb2FkIHN1Y2Nlc3MgY2FsbGJhY2sgdG8gZGV0ZXJtaW5lIGlmIGFsbCBmaWxlcyBhcmUgdXBsb2FkZWQuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjguM1xuXHQgKlxuXHQgKiBAcGFyYW0ge0V2ZW50fSBlIEV2ZW50LlxuXHQgKiBAcGFyYW0ge2pRdWVyeX0gJGZvcm0gRm9ybS5cblx0ICovXG5cdGZ1bmN0aW9uIGNvbWJpbmVkVXBsb2Fkc1NpemVPayggZSwgJGZvcm0gKSB7XG5cblx0XHRpZiAoIGFueVVwbG9hZHNJblByb2dyZXNzKCkgKSB7XG5cdFx0XHRkaXNhYmxlU3VibWl0QnV0dG9uKCAkZm9ybSApO1xuXHRcdH1cblx0fVxuXG5cdC8qKlxuXHQgKiBFdmVudHMuXG5cdCAqXG5cdCAqIEBzaW5jZSAxLjguMVxuXHQgKi9cblx0ZnVuY3Rpb24gZXZlbnRzKCkge1xuXG5cdFx0JCggJy5kcm9wem9uZS1pbnB1dCcgKVxuXHRcdFx0Lm9uKCAnZm9jdXMnLCBkcm9wem9uZUlucHV0Rm9jdXMgKVxuXHRcdFx0Lm9uKCAnYmx1cicsIGRyb3B6b25lSW5wdXRCbHVyIClcblx0XHRcdC5vbiggJ2tleXByZXNzJywgZHJvcHpvbmVJbnB1dEtleXByZXNzICk7XG5cblx0XHQkKCAnLndwZm9ybXMtdXBsb2FkZXInIClcblx0XHRcdC5vbiggJ2NsaWNrJywgZHJvcHpvbmVDbGljayApO1xuXG5cdFx0JCggJ2Zvcm0ud3Bmb3Jtcy1mb3JtJyApXG5cdFx0XHQub24oICd3cGZvcm1zQ29tYmluZWRVcGxvYWRzU2l6ZU9rJywgY29tYmluZWRVcGxvYWRzU2l6ZU9rICk7XG5cdH1cblxuXHQvKipcblx0ICogRE9NQ29udGVudExvYWRlZCBoYW5kbGVyLlxuXHQgKlxuXHQgKiBAc2luY2UgMS41LjZcblx0ICovXG5cdGZ1bmN0aW9uIHJlYWR5KCkge1xuXG5cdFx0d2luZG93LndwZm9ybXMgPSB3aW5kb3cud3Bmb3JtcyB8fCB7fTtcblx0XHR3aW5kb3cud3Bmb3Jtcy5kcm9wem9uZXMgPSBbXS5zbGljZS5jYWxsKCBkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKCAnLndwZm9ybXMtdXBsb2FkZXInICkgKS5tYXAoIGRyb3Bab25lSW5pdCApO1xuXG5cdFx0ZXZlbnRzKCk7XG5cdH1cblxuXHQvKipcblx0ICogTW9kZXJuIEZpbGUgVXBsb2FkIGVuZ2luZS5cblx0ICpcblx0ICogQHNpbmNlIDEuNi4wXG5cdCAqL1xuXHR2YXIgd3Bmb3Jtc01vZGVybkZpbGVVcGxvYWQgPSB7XG5cblx0XHQvKipcblx0XHQgKiBTdGFydCB0aGUgaW5pdGlhbGl6YXRpb24uXG5cdFx0ICpcblx0XHQgKiBAc2luY2UgMS42LjBcblx0XHQgKi9cblx0XHRpbml0OiBmdW5jdGlvbigpIHtcblxuXHRcdFx0aWYgKCBkb2N1bWVudC5yZWFkeVN0YXRlID09PSAnbG9hZGluZycgKSB7XG5cdFx0XHRcdGRvY3VtZW50LmFkZEV2ZW50TGlzdGVuZXIoICdET01Db250ZW50TG9hZGVkJywgcmVhZHkgKTtcblx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdHJlYWR5KCk7XG5cdFx0XHR9XG5cdFx0fSxcblx0fTtcblxuXHQvLyBDYWxsIGluaXQgYW5kIHNhdmUgaW4gZ2xvYmFsIHZhcmlhYmxlLlxuXHR3cGZvcm1zTW9kZXJuRmlsZVVwbG9hZC5pbml0KCk7XG5cdHdpbmRvdy53cGZvcm1zTW9kZXJuRmlsZVVwbG9hZCA9IHdwZm9ybXNNb2Rlcm5GaWxlVXBsb2FkO1xuXG59KCBqUXVlcnkgKSApO1xuIl0sIm1hcHBpbmdzIjoiQUFBQTtBQUNBLFlBQVk7O0FBQUMsU0FBQUEsUUFBQUMsQ0FBQSxzQ0FBQUQsT0FBQSx3QkFBQUUsTUFBQSx1QkFBQUEsTUFBQSxDQUFBQyxRQUFBLGFBQUFGLENBQUEsa0JBQUFBLENBQUEsZ0JBQUFBLENBQUEsV0FBQUEsQ0FBQSx5QkFBQUMsTUFBQSxJ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
},{}]},{},[1])