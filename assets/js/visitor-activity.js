/* North Mountain Media build: 20260727-site-controls-landing-v60 */
(() => {
  'use strict';
  const installLegacyMusicNavigationFix = () => {
    const destination = new URL(
      'music-library.php?v=49',
      document.baseURI
    ).href;

    const isMusicTrigger = (element) => {
      if (!(element instanceof Element)) {
        return false;
      }

      if (element.matches('[data-music-library-open]')) {
        return true;
      }

      const label = String(
        element.textContent || ''
      ).replace(/\s+/g, ' ').trim().toLowerCase();

      return (
        label === 'music library'
        && Boolean(
          element.closest(
            '.sidebar-nav, .conversation-nav, .sidebar-section'
          )
        )
      );
    };

    const replaceLegacyButton = () => {
      const button = document.querySelector(
        '[data-music-library-open]'
      );

      if (!button || button.tagName === 'A') {
        return;
      }

      const link = document.createElement('a');
      link.href = destination;
      link.className = button.className;
      link.replaceChildren(
        ...[...button.childNodes].map(
          (node) => node.cloneNode(true)
        )
      );
      link.setAttribute(
        'aria-label',
        button.getAttribute('aria-label')
        || 'Open Music Library'
      );
      button.replaceWith(link);
    };

    replaceLegacyButton();

    document.addEventListener(
      'click',
      (event) => {
        const trigger = event.target.closest(
          '[data-music-library-open], .sidebar-nav a, .sidebar-nav button'
        );

        if (!isMusicTrigger(trigger)) {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        window.location.assign(destination);
      },
      true
    );
  };

  installLegacyMusicNavigationFix();


  const endpoint = new URL(
    'api/visitor-activity.php',
    document.baseURI
  ).toString();
  const startedAt = Date.now();
  let engagementSent = false;
  const recentKeys = new Map();

  const safeText = (value, maximum = 1000) =>
    String(value ?? '').trim().slice(0, maximum);

  const browserFamily = () => {
    const value = navigator.userAgent.toLowerCase();

    if (value.includes('edg/')) return 'Edge';
    if (value.includes('opr/')) return 'Opera';
    if (value.includes('firefox/')) return 'Firefox';
    if (value.includes('chrome/')) return 'Chrome';
    if (value.includes('safari/')) return 'Safari';

    return 'Other';
  };

  const deviceType = () => {
    const value = navigator.userAgent.toLowerCase();

    if (
      value.includes('ipad')
      || value.includes('tablet')
    ) {
      return 'tablet';
    }

    if (
      value.includes('mobile')
      || value.includes('iphone')
      || value.includes('android')
    ) {
      return 'mobile';
    }

    return 'desktop';
  };

  const utmValues = () => {
    const query = new URLSearchParams(location.search);

    return {
      utm_source: safeText(query.get('utm_source'), 190),
      utm_medium: safeText(query.get('utm_medium'), 190),
      utm_campaign: safeText(query.get('utm_campaign'), 190),
      utm_term: safeText(query.get('utm_term'), 190),
      utm_content: safeText(query.get('utm_content'), 190),
    };
  };

  const basePayload = () => ({
    page_path: `${location.pathname}${location.search}`,
    referrer: document.referrer,
    device_type: deviceType(),
    browser_family: browserFamily(),
    platform: safeText(
      navigator.userAgentData?.platform
      || navigator.platform,
      80
    ),
    language: safeText(navigator.language, 40),
    timezone: safeText(
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      100
    ),
    viewport_width: Math.max(
      0,
      Math.round(window.innerWidth || 0)
    ),
    viewport_height: Math.max(
      0,
      Math.round(window.innerHeight || 0)
    ),
    ...utmValues(),
  });

  const shouldDeduplicate = (
    eventType,
    portfolioSlug,
    eventLabel
  ) => {
    const key = [
      eventType,
      portfolioSlug,
      eventLabel,
      location.pathname,
    ].join('|');
    const previous = recentKeys.get(key) || 0;
    const now = Date.now();

    recentKeys.set(key, now);

    return now - previous < 900;
  };

  const transmit = (
    payload,
    preferBeacon = false
  ) => {
    const body = JSON.stringify(payload);

    if (
      preferBeacon
      && navigator.sendBeacon
      && navigator.sendBeacon(
        endpoint,
        new Blob(
          [body],
          { type: 'application/json' }
        )
      )
    ) {
      return Promise.resolve(true);
    }

    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      keepalive: preferBeacon,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body,
    })
      .then(() => true)
      .catch(() => false);
  };

  const track = (
    eventType,
    options = {}
  ) => {
    const eventLabel = safeText(
      options.event_label,
      190
    );
    const portfolioSlug = safeText(
      options.portfolio_slug,
      190
    );

    if (
      options.deduplicate !== false
      && shouldDeduplicate(
        eventType,
        portfolioSlug,
        eventLabel
      )
    ) {
      return Promise.resolve(false);
    }

    return transmit(
      {
        ...basePayload(),
        event_type: safeText(eventType, 64),
        event_label: eventLabel,
        target_url: safeText(
          options.target_url,
          1000
        ),
        duration_seconds: Math.max(
          0,
          Math.min(
            86400,
            Math.round(
              Number(options.duration_seconds || 0)
            )
          )
        ),
        portfolio_slug: portfolioSlug,
        metadata: (
          options.metadata
          && typeof options.metadata === 'object'
        )
          ? options.metadata
          : {},
      },
      Boolean(options.prefer_beacon)
    );
  };

  const sendEngagement = () => {
    if (engagementSent) return;

    engagementSent = true;
    const duration = Math.max(
      1,
      Math.min(
        7200,
        Math.round((Date.now() - startedAt) / 1000)
      )
    );

    track(
      'page_engagement',
      {
        duration_seconds: duration,
        prefer_beacon: true,
        deduplicate: false,
        metadata: {
          page_title: safeText(document.title, 190),
        },
      }
    );
  };

  window.NMMVisitorActivity = {
    track,
    pageView: () => track(
      'page_view',
      {
        deduplicate: false,
        metadata: {
          page_title: safeText(document.title, 190),
        },
      }
    ),
  };

  document.addEventListener('click', (event) => {
    const download = event.target.closest(
      'a[href*="download"],a[href$=".pdf"]'
    );

    if (
      download
      && /resume/i.test(
        `${download.textContent || ''} ${download.href || ''}`
      )
    ) {
      track('resume_download', {
        event_label: safeText(
          download.textContent,
          190
        ),
        target_url: download.href,
      });
    }

    const portfolioLink = event.target.closest(
      '[data-portfolio-main-link]'
    );

    if (portfolioLink) {
      track('portfolio_link_click', {
        event_label: safeText(
          portfolioLink.dataset.projectTitle
          || portfolioLink.textContent,
          190
        ),
        portfolio_slug:
          portfolioLink.dataset.portfolioSlug,
        target_url: portfolioLink.href,
      });
    }

    const galleryImage = event.target.closest(
      '[data-portfolio-gallery-image]'
    );

    if (galleryImage) {
      track('portfolio_gallery', {
        event_label:
          galleryImage.dataset.projectTitle,
        portfolio_slug:
          galleryImage.dataset.portfolioSlug,
        metadata: {
          image_alt: safeText(
            galleryImage.getAttribute('alt'),
            500
          ),
        },
      });
    }
  });

  window.addEventListener(
    'pagehide',
    sendEngagement,
    { capture: true }
  );
  document.addEventListener(
    'visibilitychange',
    () => {
      if (document.visibilityState === 'hidden') {
        sendEngagement();
      }
    }
  );

  window.NMMVisitorActivity.pageView();
})();
