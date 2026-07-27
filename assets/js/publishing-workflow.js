/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';

  const endpoint = new URL(
    '../api/publishing-workflow.php',
    document.baseURI
  ).href;

  const formValue = (form, name) => {
    const field = form.elements.namedItem(name);

    if (!field) {
      return '';
    }

    if (
      field instanceof RadioNodeList
    ) {
      return field.value;
    }

    if (
      field instanceof HTMLInputElement
      && field.type === 'checkbox'
    ) {
      return field.checked ? 1 : 0;
    }

    return String(field.value || '');
  };

  const postJson = async (payload, token) => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json().catch(() => ({
      ok: false,
      message: 'The publishing response could not be read.'
    }));

    if (!response.ok || !data.ok) {
      throw new Error(
        data.message || 'The publishing action failed.'
      );
    }

    return data;
  };

  document.querySelectorAll(
    '[data-publishing-autosave]'
  ).forEach((form) => {
    const system = String(
      form.dataset.publishingAutosave || ''
    ).trim();
    const postId = Number(
      form.querySelector('[name="id"]')?.value || 0
    );
    const token = String(
      form.querySelector('[name="_token"]')?.value || ''
    );
    const status = form.querySelector(
      '[data-autosave-status]'
    );

    if (
      !['blog', 'resume'].includes(system)
      || postId <= 0
      || !token
    ) {
      status?.replaceChildren(
        document.createTextNode(
          'Save once to enable autosave.'
        )
      );
      return;
    }

    const fields = system === 'blog'
      ? [
          'author_user_id',
          'title',
          'slug',
          'status',
          'featured',
          'category',
          'excerpt',
          'body',
          'tags',
          'seo_title',
          'seo_description',
          'canonical_url',
          'published_at'
        ]
      : [
          'title',
          'slug',
          'post_type',
          'column_name',
          'status',
          'featured',
          'sort_order',
          'section_label',
          'subtitle',
          'organization',
          'location',
          'date_label',
          'start_date',
          'end_date',
          'is_current',
          'summary',
          'body',
          'achievements',
          'skills',
          'link_url',
          'link_label',
          'published_at'
        ];

    let timer = 0;
    let saving = false;
    let dirty = false;

    const setStatus = (message, state = '') => {
      if (!status) {
        return;
      }

      status.replaceChildren(
        document.createTextNode(message)
      );
      status.dataset.state = state;
    };

    const save = async () => {
      if (saving || !dirty) {
        return;
      }

      saving = true;
      dirty = false;
      setStatus('Saving…', 'saving');

      const payload = {
        action: `autosave_${system}`,
        post_id: postId
      };

      fields.forEach((name) => {
        payload[name] = formValue(form, name);
      });

      try {
        const data = await postJson(payload, token);
        const savedAt = new Date(
          data.saved_at || Date.now()
        );

        setStatus(
          `Autosaved ${savedAt.toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit'
          })}`,
          'saved'
        );
      } catch (error) {
        dirty = true;
        setStatus(
          error instanceof Error
            ? error.message
            : 'Autosave failed.',
          'error'
        );
      } finally {
        saving = false;
      }
    };

    const queue = () => {
      dirty = true;
      setStatus('Unsaved changes', 'dirty');
      window.clearTimeout(timer);
      timer = window.setTimeout(save, 1800);
    };

    form.addEventListener('input', queue);
    form.addEventListener('change', queue);
    form.addEventListener('submit', () => {
      dirty = false;
      window.clearTimeout(timer);
    });

    window.addEventListener('beforeunload', (event) => {
      if (!dirty) {
        return;
      }

      event.preventDefault();
      event.returnValue = '';
    });

    setStatus('Autosave ready', 'ready');
  });


  document.querySelectorAll(
    '[data-apply-autosave]'
  ).forEach((button) => {
    button.addEventListener('click', () => {
      const form = document.querySelector(
        '[data-publishing-autosave]'
      );

      if (!form) {
        return;
      }

      let payload = {};

      try {
        payload = JSON.parse(
          button.dataset.autosave || '{}'
        );
      } catch {
        payload = {};
      }

      Object.entries(payload).forEach(
        ([name, value]) => {
          const field = form.elements.namedItem(name);

          if (!field) {
            return;
          }

          if (
            field instanceof HTMLInputElement
            && field.type === 'checkbox'
          ) {
            field.checked = Boolean(Number(value));
            field.dispatchEvent(
              new Event('change', {
                bubbles: true
              })
            );
            return;
          }

          field.value = value == null
            ? ''
            : String(value);
          field.dispatchEvent(
            new Event('input', {
              bubbles: true
            })
          );
        }
      );

      button.disabled = true;
      button.replaceChildren(
        document.createTextNode(
          'Autosave applied'
        )
      );

      form.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    });
  });

  const lists = [
    ...document.querySelectorAll(
      '[data-resume-sort-list]'
    )
  ];

  if (lists.length) {
    let dragging = null;
    const token = String(
      document.querySelector(
        '[data-resume-sort-token]'
      )?.value || ''
    );
    const status = document.querySelector(
      '[data-resume-sort-status]'
    );

    const setSortStatus = (
      message,
      state = ''
    ) => {
      if (!status) {
        return;
      }

      status.replaceChildren(
        document.createTextNode(message)
      );
      status.dataset.state = state;
    };

    const saveOrder = async () => {
      if (!token) {
        setSortStatus(
          'Refresh the page before saving order.',
          'error'
        );
        return;
      }

      const groups = {};

      lists.forEach((list) => {
        const column = String(
          list.dataset.resumeSortList || ''
        );

        list.querySelectorAll(
          '[data-resume-sort-item]'
        ).forEach((item) => {
          const label = item.querySelector(
            '.resume-admin-order span'
          );

          label?.replaceChildren(
            document.createTextNode(column)
          );
        });

        groups[column] = [
          ...list.querySelectorAll(
            '[data-resume-sort-item]'
          )
        ].map((item) => Number(
          item.dataset.resumeSortItem || 0
        )).filter(Boolean);
      });

      setSortStatus('Saving order…', 'saving');

      try {
        await postJson({
          action: 'reorder_resume',
          groups
        }, token);

        setSortStatus('Order saved', 'saved');
      } catch (error) {
        setSortStatus(
          error instanceof Error
            ? error.message
            : 'Order could not be saved.',
          'error'
        );
      }
    };

    const itemAfterPointer = (list, y) => {
      const items = [
        ...list.querySelectorAll(
          '[data-resume-sort-item]:not(.is-dragging)'
        )
      ];

      return items.reduce(
        (closest, item) => {
          const box = item.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;

          if (
            offset < 0
            && offset > closest.offset
          ) {
            return {
              offset,
              element: item
            };
          }

          return closest;
        },
        {
          offset: Number.NEGATIVE_INFINITY,
          element: null
        }
      ).element;
    };

    document.querySelectorAll(
      '[data-resume-move]'
    ).forEach((button) => {
      button.addEventListener('click', () => {
        const item = button.closest(
          '[data-resume-sort-item]'
        );
        const direction = String(
          button.dataset.resumeMove || ''
        );

        if (!item) {
          return;
        }

        if (direction === 'up') {
          const previous = item.previousElementSibling;

          if (previous) {
            item.parentElement.insertBefore(
              item,
              previous
            );
          }
        }

        if (direction === 'down') {
          const next = item.nextElementSibling;

          if (next) {
            item.parentElement.insertBefore(
              next,
              item
            );
          }
        }

        saveOrder();
      });
    });

    lists.forEach((list) => {
      list.addEventListener('dragstart', (event) => {
        const item = event.target.closest(
          '[data-resume-sort-item]'
        );

        if (!item) {
          return;
        }

        dragging = item;
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(
          'text/plain',
          item.dataset.resumeSortItem || ''
        );
        setSortStatus(
          'Drag to a new position',
          'dirty'
        );
      });

      list.addEventListener('dragover', (event) => {
        event.preventDefault();

        if (!dragging) {
          return;
        }

        const after = itemAfterPointer(
          list,
          event.clientY
        );

        if (after) {
          list.insertBefore(dragging, after);
        } else {
          list.append(dragging);
        }
      });

      list.addEventListener('drop', (event) => {
        event.preventDefault();
      });

      list.addEventListener('dragend', () => {
        if (!dragging) {
          return;
        }

        dragging.classList.remove('is-dragging');
        dragging = null;
        saveOrder();
      });
    });
  }

  document.querySelectorAll(
    '[data-focal-control]'
  ).forEach((control) => {
    const card = control.closest(
      '[data-media-card]'
    );
    const preview = card?.querySelector(
      '[data-media-preview]'
    );
    const output = card?.querySelector(
      `[data-focal-output="${control.name}"]`
    );

    const update = () => {
      const x = Number(
        card?.querySelector('[name="focal_x"]')
          ?.value || 50
      );
      const y = Number(
        card?.querySelector('[name="focal_y"]')
          ?.value || 50
      );

      if (preview) {
        preview.style.objectPosition = `${x}% ${y}%`;
      }

      output?.replaceChildren(
        document.createTextNode(
          `${Number(control.value).toFixed(0)}%`
        )
      );
    };

    control.addEventListener('input', update);
    update();
  });
})();
