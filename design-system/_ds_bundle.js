/* @ds-bundle: {"format":4,"namespace":"MarktlaufKirchseeonDesignSystem_48e1b5","components":[{"name":"Alert","sourcePath":"components/core/Alert.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Chip","sourcePath":"components/core/Chip.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"FormField","sourcePath":"components/forms/FormField.jsx"},{"name":"Tabs","sourcePath":"components/forms/Tabs.jsx"},{"name":"DataTable","sourcePath":"components/orga/DataTable.jsx"},{"name":"Kachel","sourcePath":"components/orga/Kachel.jsx"},{"name":"KpiTile","sourcePath":"components/orga/KpiTile.jsx"},{"name":"OrgaSidebar","sourcePath":"components/orga/OrgaSidebar.jsx"},{"name":"Countdown","sourcePath":"components/site/Countdown.jsx"},{"name":"KoopBadge","sourcePath":"components/site/KoopBadge.jsx"},{"name":"LiveTicker","sourcePath":"components/site/LiveTicker.jsx"},{"name":"LogoPlakette","sourcePath":"components/site/LogoPlakette.jsx"},{"name":"RouteCard","sourcePath":"components/site/RouteCard.jsx"},{"name":"RunCard","sourcePath":"components/site/RunCard.jsx"},{"name":"SectionHeading","sourcePath":"components/site/SectionHeading.jsx"},{"name":"SponsorMarquee","sourcePath":"components/site/SponsorMarquee.jsx"},{"name":"TimelineItem","sourcePath":"components/site/TimelineItem.jsx"}],"sourceHashes":{"components/core/Alert.jsx":"0c4ae8e4efe3","components/core/Badge.jsx":"4d319710b4f8","components/core/Button.jsx":"0323287e38b3","components/core/Card.jsx":"da97e614a315","components/core/Chip.jsx":"f93a78460166","components/forms/Checkbox.jsx":"1b0650065f6d","components/forms/FormField.jsx":"7d7ba1a518ec","components/forms/Tabs.jsx":"3512c3e5cb01","components/orga/DataTable.jsx":"2bd49a4eb93d","components/orga/Kachel.jsx":"a934c71bd0d4","components/orga/KpiTile.jsx":"9e6cd5d67e5c","components/orga/OrgaSidebar.jsx":"d03c7176924c","components/site/Countdown.jsx":"a2e0536dca24","components/site/KoopBadge.jsx":"fc8ae42c5743","components/site/LiveTicker.jsx":"131317158faf","components/site/LogoPlakette.jsx":"eb7593ffd860","components/site/RouteCard.jsx":"23d34a6ceb74","components/site/RunCard.jsx":"62ebba007535","components/site/SectionHeading.jsx":"dcdffdaf3a76","components/site/SponsorMarquee.jsx":"766c63733501","components/site/TimelineItem.jsx":"cd78da37d8d2","ui_kits/orga_dashboard/Cockpit.jsx":"11096c9f20ab","ui_kits/orga_dashboard/HelferListe.jsx":"08ffea96135d","ui_kits/orga_dashboard/Login.jsx":"740d5c7636b8","ui_kits/orga_dashboard/navItems.js":"66bddd4f4998","ui_kits/website/Connect.jsx":"1433a7a2f0cd","ui_kits/website/Footer.jsx":"10831b1402e0","ui_kits/website/Header.jsx":"a7591597e19b","ui_kits/website/Hero.jsx":"ea27609f6e9c","ui_kits/website/Sections.jsx":"a26ab66cdc4e"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.MarktlaufKirchseeonDesignSystem_48e1b5 = window.MarktlaufKirchseeonDesignSystem_48e1b5 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/core/Alert.jsx
try { (() => {
const TONES = {
  success: {
    background: 'var(--success-bg)',
    color: 'var(--success-fg)',
    border: 'transparent'
  },
  error: {
    background: 'var(--error-bg)',
    color: 'var(--error)',
    border: 'transparent'
  },
  formSuccess: {
    background: 'var(--form-success-bg)',
    color: 'var(--form-success-fg)',
    border: 'var(--form-success-border)'
  },
  formError: {
    background: 'var(--form-error-bg)',
    color: 'var(--form-error-fg)',
    border: 'var(--form-error-border)'
  }
};
function Alert({
  tone = 'success',
  children,
  style
}) {
  const t = TONES[tone];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      padding: tone.startsWith('form') ? '1rem' : '0.65rem 0.9rem',
      borderRadius: 'var(--radius-orga)',
      border: '1px solid ' + t.border,
      background: t.background,
      color: t.color,
      fontSize: '0.9rem',
      fontWeight: tone.startsWith('form') ? 'var(--weight-semibold)' : 'var(--weight-regular)',
      textAlign: tone.startsWith('form') ? 'center' : 'left',
      ...style
    }
  }, children);
}
Object.assign(__ds_scope, { Alert });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Alert.jsx", error: String((e && e.message) || e) }); }

// components/core/Badge.jsx
try { (() => {
const TONES = {
  neu: {
    background: 'var(--warn-bg)',
    color: 'var(--warn-fg)'
  },
  bestaetigt: {
    background: 'var(--success-bg)',
    color: 'var(--success-fg)'
  },
  abgelehnt: {
    background: 'var(--error-bg)',
    color: 'var(--error)'
  },
  neutral: {
    background: 'var(--orga-border)',
    color: 'var(--orga-text-light)'
  },
  gruen: {
    background: 'var(--color-primary)',
    color: 'var(--white)'
  },
  orange: {
    background: 'var(--color-accent)',
    color: 'var(--white)'
  },
  info: {
    background: 'var(--ticker-info)',
    color: 'var(--white)'
  }
};
function Badge({
  tone = 'neutral',
  uppercase = false,
  children,
  style
}) {
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-block',
      padding: uppercase ? '9px 18px' : '0.25rem 0.5rem',
      borderRadius: uppercase ? 'var(--radius-pill)' : 'var(--radius-sm)',
      fontFamily: 'var(--font-main)',
      fontSize: uppercase ? '14px' : '0.75rem',
      fontWeight: uppercase ? 'var(--weight-bold)' : 'var(--weight-medium)',
      letterSpacing: uppercase ? 'var(--tracking-label)' : undefined,
      textTransform: uppercase ? 'uppercase' : 'none',
      ...TONES[tone],
      ...style
    }
  }, children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const VARIANTS = {
  primary: {
    background: 'var(--color-primary)',
    color: 'var(--white)',
    boxShadow: 'var(--shadow-sm)'
  },
  secondary: {
    background: 'var(--white)',
    color: 'var(--gray-900)',
    boxShadow: 'var(--shadow-sm)'
  },
  gold: {
    background: 'var(--color-accent-yellow)',
    color: 'var(--color-ink)',
    boxShadow: 'var(--shadow-gold-cta)'
  },
  onGradient: {
    background: 'rgba(255,255,255,.14)',
    color: 'var(--white)',
    border: '1.5px solid rgba(255,255,255,.7)'
  },
  outline: {
    background: 'rgba(0,0,0,.04)',
    color: 'var(--gray-700)',
    border: '1.5px solid var(--gray-300)'
  }
};
const HOVER = {
  primary: 'var(--color-primary-dark)',
  secondary: 'var(--gray-50)',
  gold: '#e5aa18',
  onGradient: 'rgba(255,255,255,.24)',
  outline: 'var(--gray-100)'
};
const SIZES = {
  sm: {
    padding: '0.3rem 0.65rem',
    fontSize: '0.8rem'
  },
  md: {
    padding: '0.75rem 1.5rem',
    fontSize: 'var(--text-base)'
  },
  lg: {
    padding: '16px 32px',
    fontSize: '18px'
  }
};
function Button({
  variant = 'primary',
  size = 'md',
  pill = false,
  block = false,
  disabled = false,
  href,
  onClick,
  children,
  style
}) {
  const [hover, setHover] = React.useState(false);
  const base = {
    display: block ? 'block' : 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '8px',
    width: block ? '100%' : undefined,
    fontFamily: 'var(--font-main)',
    fontWeight: 'var(--weight-semibold)',
    textAlign: 'center',
    border: 'none',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.6 : 1,
    borderRadius: pill ? 'var(--radius-pill)' : '6px',
    transition: 'all var(--duration-fast) var(--ease)',
    transform: hover && !disabled ? 'var(--lift-hover)' : 'none',
    ...SIZES[size],
    ...VARIANTS[variant],
    ...(hover && !disabled ? {
      background: HOVER[variant]
    } : null),
    ...style
  };
  const props = {
    style: base,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    onClick: disabled ? undefined : onClick
  };
  return href && !disabled ? /*#__PURE__*/React.createElement("a", _extends({
    href: href
  }, props), children) : /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    disabled: disabled
  }, props), children);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function Card({
  hoverable = false,
  padding = 'var(--space-lg)',
  accent,
  children,
  style
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--surface-card)',
      border: '1px solid var(--border-card)',
      borderTop: accent ? '4px solid ' + accent : undefined,
      borderRadius: 'var(--radius-lg)',
      padding,
      boxShadow: hoverable && hover ? 'var(--shadow-md)' : 'var(--shadow-sm)',
      borderColor: hoverable && hover ? 'var(--color-primary)' : undefined,
      transform: hoverable && hover ? 'var(--lift-card)' : 'none',
      transition: 'all var(--duration-med) var(--ease)',
      display: 'flex',
      flexDirection: 'column',
      ...style
    }
  }, children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Chip.jsx
try { (() => {
function Chip({
  time,
  tilted = false,
  tone = 'grau',
  children,
  style
}) {
  const tones = {
    grau: {
      background: 'var(--gray-100)',
      color: 'var(--color-ink)'
    },
    weiss: {
      background: 'var(--white)',
      color: 'var(--color-deep-green)',
      boxShadow: '0 6px 16px rgba(0,0,0,.14)'
    }
  };
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '10px',
      padding: tilted ? '8px 18px' : '11px 18px',
      borderRadius: tilted ? 'var(--radius-md)' : 'var(--radius-pill)',
      fontFamily: tilted ? 'var(--font-display)' : 'var(--font-main)',
      fontSize: tilted ? '23px' : 'var(--text-base)',
      fontWeight: 'var(--weight-semibold)',
      transform: tilted ? 'rotate(-2deg)' : 'none',
      ...tones[tone],
      ...style
    }
  }, time ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-display)',
      color: 'var(--color-primary)'
    }
  }, time) : null, children);
}
Object.assign(__ds_scope, { Chip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Chip.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
function Checkbox({
  name,
  checked,
  onChange,
  required = false,
  children
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'flex',
      alignItems: 'flex-start',
      gap: 'var(--space-xs)',
      cursor: 'pointer',
      fontFamily: 'var(--font-main)',
      fontSize: '0.9rem',
      lineHeight: 1.4,
      color: 'var(--gray-700)'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    name: name,
    checked: checked,
    onChange: onChange,
    required: required,
    style: {
      width: '1.125rem',
      height: '1.125rem',
      marginTop: '0.125rem',
      flexShrink: 0,
      accentColor: 'var(--color-primary)',
      cursor: 'pointer'
    }
  }), /*#__PURE__*/React.createElement("span", null, children));
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/FormField.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const labelStyle = {
  display: 'block',
  fontFamily: 'var(--font-main)',
  fontWeight: 'var(--weight-semibold)',
  marginBottom: 'var(--space-xs)',
  color: 'var(--gray-700)',
  fontSize: '0.9rem'
};
function FormField({
  label,
  type = 'text',
  name,
  placeholder,
  required = false,
  rows = 5,
  options = [],
  value,
  onChange,
  hint,
  dense = false
}) {
  const [focus, setFocus] = React.useState(false);
  const control = {
    width: '100%',
    padding: dense ? 'var(--control-pad-y) 0.6rem' : '0.75rem',
    border: '1px solid ' + (focus ? 'var(--color-primary)' : 'var(--gray-300)'),
    borderRadius: dense ? 'var(--radius-orga)' : '6px',
    fontFamily: 'inherit',
    fontSize: dense ? '0.9rem' : '1rem',
    outline: 'none',
    boxShadow: focus ? 'var(--focus-ring)' : 'none',
    transition: 'border-color var(--duration-fast) var(--ease), box-shadow var(--duration-fast) var(--ease)'
  };
  const shared = {
    name,
    placeholder,
    required,
    value,
    onChange,
    style: control,
    onFocus: () => setFocus(true),
    onBlur: () => setFocus(false)
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: dense ? '1rem' : 'var(--space-md)',
      textAlign: 'left'
    }
  }, label ? /*#__PURE__*/React.createElement("label", {
    htmlFor: name,
    style: labelStyle
  }, label, required ? ' *' : '') : null, type === 'textarea' ? /*#__PURE__*/React.createElement("textarea", _extends({
    id: name,
    rows: rows
  }, shared)) : type === 'select' ? /*#__PURE__*/React.createElement("select", _extends({
    id: name
  }, shared), placeholder ? /*#__PURE__*/React.createElement("option", {
    value: "",
    disabled: true
  }, placeholder) : null, options.map(o => /*#__PURE__*/React.createElement("option", {
    key: o,
    value: o
  }, o))) : /*#__PURE__*/React.createElement("input", _extends({
    id: name,
    type: type
  }, shared)), hint ? /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: '0.8rem',
      color: 'var(--orga-text-light)',
      marginTop: '0.25rem'
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { FormField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FormField.jsx", error: String((e && e.message) || e) }); }

// components/forms/Tabs.jsx
try { (() => {
function Tabs({
  tabs = [],
  active,
  onChange,
  children
}) {
  const [internal, setInternal] = React.useState(tabs[0] && tabs[0].id);
  const current = active !== undefined ? active : internal;
  const select = id => {
    setInternal(id);
    if (onChange) onChange(id);
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--white)',
      padding: 'var(--space-lg)',
      borderRadius: 'var(--radius-lg)',
      border: '1px solid var(--gray-200)',
      boxShadow: 'var(--shadow-md)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-xs)',
      background: 'var(--gray-200)',
      padding: 'var(--space-xs)',
      borderRadius: 'var(--radius-lg)',
      width: 'fit-content',
      margin: '0 auto var(--space-lg)'
    }
  }, tabs.map(t => {
    const on = t.id === current;
    return /*#__PURE__*/React.createElement("button", {
      key: t.id,
      type: "button",
      onClick: () => select(t.id),
      style: {
        padding: '0.6rem 1.5rem',
        border: 'none',
        background: on ? 'var(--white)' : 'none',
        fontFamily: 'inherit',
        fontWeight: 'var(--weight-semibold)',
        fontSize: '0.9rem',
        color: on ? 'var(--color-primary)' : 'var(--gray-600)',
        cursor: 'pointer',
        borderRadius: 'var(--radius-md)',
        boxShadow: on ? 'var(--shadow-sm)' : 'none',
        transition: 'all var(--duration-fast) var(--ease)'
      }
    }, t.label);
  })), /*#__PURE__*/React.createElement("div", null, typeof children === 'function' ? children(current) : children));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/orga/DataTable.jsx
try { (() => {
function DataTable({
  columns = [],
  rows = [],
  empty = 'Keine Einträge gefunden.'
}) {
  const [hoverRow, setHoverRow] = React.useState(-1);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      overflowX: 'auto',
      borderRadius: 'var(--radius-orga-card)',
      boxShadow: 'var(--shadow-card)'
    }
  }, /*#__PURE__*/React.createElement("table", {
    style: {
      width: '100%',
      borderCollapse: 'collapse',
      background: 'var(--white)',
      borderRadius: 'var(--radius-orga-card)',
      overflow: 'hidden',
      fontFamily: 'var(--font-ui)'
    }
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, columns.map(c => /*#__PURE__*/React.createElement("th", {
    key: c,
    style: {
      padding: '0.75rem',
      textAlign: 'left',
      borderBottom: '1px solid var(--orga-border)',
      background: 'var(--orga-bg)',
      fontWeight: 'var(--weight-semibold)',
      fontSize: '0.75rem',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-label)',
      color: 'var(--orga-text-light)'
    }
  }, c)))), /*#__PURE__*/React.createElement("tbody", null, rows.length === 0 ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: columns.length,
    style: {
      padding: '0.75rem',
      fontSize: '0.875rem',
      color: 'var(--orga-text-light)'
    }
  }, empty)) : rows.map((r, i) => /*#__PURE__*/React.createElement("tr", {
    key: i,
    onMouseEnter: () => setHoverRow(i),
    onMouseLeave: () => setHoverRow(-1),
    style: {
      background: hoverRow === i ? '#fafafa' : 'transparent'
    }
  }, r.map((cell, j) => /*#__PURE__*/React.createElement("td", {
    key: j,
    style: {
      padding: '0.75rem',
      textAlign: 'left',
      borderBottom: '1px solid var(--orga-border)',
      fontSize: '0.875rem',
      verticalAlign: 'top',
      color: 'var(--orga-text)'
    }
  }, cell)))))));
}
Object.assign(__ds_scope, { DataTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/orga/DataTable.jsx", error: String((e && e.message) || e) }); }

// components/orga/Kachel.jsx
try { (() => {
function Kachel({
  title,
  actions,
  children,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--white)',
      border: '1px solid var(--orga-border)',
      borderRadius: 'var(--radius-orga-card)',
      padding: '1rem 1.15rem',
      boxShadow: 'var(--shadow-card)',
      fontFamily: 'var(--font-ui)',
      fontSize: '15px',
      color: 'var(--orga-text)',
      ...style
    }
  }, title || actions ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '1rem',
      marginBottom: '0.6rem'
    }
  }, title ? /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: '0.95rem',
      color: 'var(--orga-text-light)',
      margin: 0,
      fontWeight: 'var(--weight-semibold)'
    }
  }, title) : /*#__PURE__*/React.createElement("span", null), actions) : null, children);
}
Object.assign(__ds_scope, { Kachel });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/orga/Kachel.jsx", error: String((e && e.message) || e) }); }

// components/orga/KpiTile.jsx
try { (() => {
const SIGNAL = {
  ok: 'var(--color-primary)',
  attention: 'var(--color-accent)',
  neutral: 'var(--orga-border)'
};
function KpiTile({
  title,
  value,
  label,
  signal = 'neutral',
  href = '#',
  note,
  disabled = false
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", {
    href: disabled ? undefined : href,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      display: 'flex',
      flexDirection: 'column',
      textDecoration: 'none',
      color: 'inherit',
      background: 'var(--white)',
      padding: '1.15rem',
      borderRadius: '6px',
      boxShadow: hover && !disabled ? 'var(--shadow-lg)' : 'var(--shadow-card)',
      borderLeft: '3px solid ' + SIGNAL[signal],
      transform: hover && !disabled ? 'var(--lift-subtle)' : 'none',
      opacity: disabled ? 0.7 : 1,
      fontFamily: 'var(--font-ui)',
      transition: 'box-shadow 0.15s ease, transform 0.15s ease'
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: '0.95rem',
      color: 'var(--orga-text-light)',
      marginBottom: '0.5rem',
      fontWeight: 'var(--weight-semibold)'
    }
  }, title), value !== undefined ? /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: '1.6rem',
      fontWeight: 'var(--weight-semibold)',
      color: signal === 'attention' ? 'var(--color-accent)' : 'var(--color-primary)',
      margin: 0
    }
  }, value) : null, label ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.875rem',
      color: 'var(--orga-text-light)'
    }
  }, label) : null, note ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.75rem',
      color: 'var(--orga-text-light)',
      marginTop: '0.5rem',
      fontStyle: 'italic'
    }
  }, note) : null, disabled ? null : /*#__PURE__*/React.createElement("span", {
    style: {
      marginTop: '0.25rem',
      fontSize: '0.9rem',
      fontWeight: 'var(--weight-medium)',
      color: 'var(--color-primary)'
    }
  }, "\xD6ffnen \u2192"));
}
Object.assign(__ds_scope, { KpiTile });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/orga/KpiTile.jsx", error: String((e && e.message) || e) }); }

// components/orga/OrgaSidebar.jsx
try { (() => {
function OrgaSidebar({
  base = '',
  items = [],
  active,
  user = {
    name: 'Orga',
    role: 'Admin'
  },
  onSelect
}) {
  let lastSection = null;
  return /*#__PURE__*/React.createElement("nav", {
    style: {
      width: 'var(--sidebar-width)',
      flexShrink: 0,
      background: 'var(--white)',
      borderRight: '1px solid var(--orga-border)',
      display: 'flex',
      flexDirection: 'column',
      fontFamily: 'var(--font-ui)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '1rem 1.25rem',
      borderBottom: '1px solid var(--orga-border)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '1rem'
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: '1.05rem',
      color: 'var(--color-primary)',
      margin: 0
    }
  }, "Orga-Dashboard"), /*#__PURE__*/React.createElement("img", {
    src: base + 'assets/images/logo-final.svg',
    alt: "Marktlauf",
    style: {
      height: '2.25rem',
      width: 'auto'
    }
  })), /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      padding: '0.75rem 0',
      margin: 0,
      flex: 1,
      overflowY: 'auto'
    }
  }, items.map(item => {
    const rows = [];
    if (item.section && item.section !== lastSection) {
      rows.push(/*#__PURE__*/React.createElement("li", {
        key: item.section + '-h',
        style: {
          padding: '0.9rem 1.25rem 0.4rem',
          fontSize: '0.7rem',
          textTransform: 'uppercase',
          letterSpacing: 'var(--tracking-label)',
          color: 'var(--orga-text-light)'
        }
      }, item.section));
      lastSection = item.section;
    }
    const on = item.key === active;
    rows.push(/*#__PURE__*/React.createElement("li", {
      key: item.key
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        if (onSelect) onSelect(item.key);
      },
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        padding: '0.5rem 1.25rem',
        paddingLeft: item.section ? '2.25rem' : '1.25rem',
        color: on ? 'var(--color-primary)' : 'var(--orga-text)',
        textDecoration: 'none',
        fontSize: '0.9rem',
        fontWeight: on ? 'var(--weight-medium)' : 'var(--weight-regular)',
        background: on ? 'rgba(0, 150, 64, 0.1)' : 'transparent'
      }
    }, item.label, item.badge ? /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: '0.625rem',
        padding: '0.125rem 0.5rem',
        background: 'var(--orga-border)',
        color: 'var(--orga-text-light)',
        borderRadius: '10px',
        textTransform: 'uppercase'
      }
    }, item.badge) : null)));
    return rows;
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '1rem 1.25rem',
      borderTop: '1px solid var(--orga-border)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      marginBottom: '0.75rem'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--weight-medium)',
      fontSize: '0.9rem'
    }
  }, user.name), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.75rem',
      color: 'var(--orga-text-light)'
    }
  }, user.role))));
}
Object.assign(__ds_scope, { OrgaSidebar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/orga/OrgaSidebar.jsx", error: String((e && e.message) || e) }); }

// components/site/Countdown.jsx
try { (() => {
function Countdown({
  target = '2026-09-20T10:00:00',
  onGradient = true
}) {
  const [now, setNow] = React.useState(() => Date.now());
  React.useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, []);
  const diff = Math.max(0, new Date(target).getTime() - now);
  const s = Math.floor(diff / 1000);
  const units = [{
    v: Math.floor(s / 86400),
    l: 'Tage'
  }, {
    v: Math.floor(s % 86400 / 3600),
    l: 'Stunden'
  }, {
    v: Math.floor(s % 3600 / 60),
    l: 'Minuten'
  }, {
    v: s % 60,
    l: 'Sekunden'
  }];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-md)'
    }
  }, units.map(u => /*#__PURE__*/React.createElement("div", {
    key: u.l,
    style: {
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontSize: 'clamp(2rem, 5vw, 3rem)',
      fontWeight: 'var(--weight-bold)',
      lineHeight: 1,
      color: onGradient ? 'var(--white)' : 'var(--color-primary)'
    }
  }, u.v), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-main)',
      fontSize: '0.875rem',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-caps)',
      color: onGradient ? 'rgba(255,255,255,.8)' : 'var(--gray-500)'
    }
  }, u.l))));
}
Object.assign(__ds_scope, { Countdown });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/Countdown.jsx", error: String((e && e.message) || e) }); }

// components/site/KoopBadge.jsx
try { (() => {
function KoopBadge({
  base = '',
  text = 'In Kooperation mit',
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'inline-flex',
      flexDirection: 'column',
      alignItems: 'flex-start',
      gap: '7px',
      background: 'rgba(255,255,255,.94)',
      padding: '12px 20px',
      borderRadius: 'var(--radius-plakette)',
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-main)',
      fontSize: '11px',
      fontWeight: 'var(--weight-bold)',
      letterSpacing: 'var(--tracking-caps)',
      color: 'var(--color-deep-green)',
      textTransform: 'uppercase'
    }
  }, text), /*#__PURE__*/React.createElement("img", {
    src: base + 'assets/images/Wort-u-Bildmarke-Gemeinde.png',
    alt: "Markt Kirchseeon",
    style: {
      height: '64px',
      width: 'auto',
      display: 'block'
    }
  }));
}
Object.assign(__ds_scope, { KoopBadge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/KoopBadge.jsx", error: String((e && e.message) || e) }); }

// components/site/LiveTicker.jsx
try { (() => {
const TYP = {
  info: 'var(--ticker-info)',
  warnung: 'var(--ticker-warn)',
  ergebnis: 'var(--color-primary)'
};
function LiveTicker({
  entries = []
}) {
  if (!entries.length) return null;
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--ticker-bg)',
      color: 'var(--white)',
      padding: '0.6rem 1rem',
      fontFamily: 'var(--font-main)',
      fontSize: '0.88rem',
      lineHeight: 1.4
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '900px',
      margin: '0 auto',
      display: 'flex',
      alignItems: 'flex-start',
      gap: '0.75rem'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '0.35rem',
      fontWeight: 'var(--weight-bold)',
      whiteSpace: 'nowrap',
      paddingTop: '1px',
      color: 'var(--ticker-accent)',
      textTransform: 'uppercase',
      fontSize: '0.78rem',
      letterSpacing: '0.04em'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: '7px',
      height: '7px',
      borderRadius: '50%',
      background: 'var(--ticker-accent)',
      flexShrink: 0
    }
  }), "Live"), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      gap: '0.3rem'
    }
  }, entries.map((e, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: '0.5rem'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.72rem',
      fontWeight: 'var(--weight-bold)',
      padding: '0.1rem 0.45rem',
      borderRadius: '10px',
      flexShrink: 0,
      background: TYP[e.typ] || TYP.info,
      color: 'var(--white)'
    }
  }, e.typ), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, e.text))))));
}
Object.assign(__ds_scope, { LiveTicker });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/LiveTicker.jsx", error: String((e && e.message) || e) }); }

// components/site/LogoPlakette.jsx
try { (() => {
function LogoPlakette({
  base = '',
  plain = false,
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: plain ? 'var(--space-md)' : '14px',
      background: plain ? 'transparent' : 'var(--surface-plakette)',
      padding: plain ? 0 : '8px 18px',
      borderRadius: plain ? 0 : 'var(--radius-plakette)',
      boxShadow: plain ? 'none' : 'var(--shadow-plakette)',
      ...style
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: base + 'assets/images/marktlauf-wordmark.png',
    alt: "Marktlauf Kirchseeon",
    style: {
      height: plain ? '39px' : '42px',
      width: 'auto',
      objectFit: 'contain'
    }
  }), plain ? null : /*#__PURE__*/React.createElement("span", {
    style: {
      width: '1px',
      height: '38px',
      background: 'var(--border-divider)'
    }
  }), /*#__PURE__*/React.createElement("img", {
    src: base + 'assets/images/ATSV_Logo-750x968.png',
    alt: "ATSV Kirchseeon 1906",
    style: {
      height: plain ? '42.5px' : '46px',
      width: 'auto',
      objectFit: 'contain'
    }
  }));
}
Object.assign(__ds_scope, { LogoPlakette });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/LogoPlakette.jsx", error: String((e && e.message) || e) }); }

// components/site/RouteCard.jsx
try { (() => {
function RouteCard({
  title,
  distance,
  age,
  disclaimer,
  gpxHref,
  blocked = false,
  blockedText = 'Karte folgt nach Freigabe'
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--white)',
      border: '1px solid ' + (hover ? 'var(--color-primary)' : 'var(--gray-200)'),
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: hover ? 'var(--shadow-md)' : 'var(--shadow-sm)',
      transform: hover ? 'var(--lift-card)' : 'none',
      transition: 'all var(--duration-med) var(--ease)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: '180px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      textAlign: 'center',
      padding: 'var(--space-md)',
      color: 'var(--gray-500)',
      fontFamily: 'var(--font-main)',
      background: blocked ? 'repeating-linear-gradient(45deg, var(--gray-100), var(--gray-100) 12px, var(--gray-200) 12px, var(--gray-200) 24px)' : 'var(--gray-100)',
      fontStyle: blocked ? 'normal' : 'italic',
      fontWeight: blocked ? 'var(--weight-bold)' : 'var(--weight-regular)'
    }
  }, blocked ? blockedText : 'Kartenvorschau'), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-md)',
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-bold)',
      fontSize: '1.25rem',
      color: 'var(--gray-800)'
    }
  }, title), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-heading)',
      fontWeight: 'var(--weight-black)',
      fontSize: '1.25rem',
      color: 'var(--gray-900)'
    }
  }, distance)), age ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.85rem',
      color: '#6c757d',
      marginTop: '-4px'
    }
  }, age) : null, disclaimer ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.85rem',
      color: 'var(--gray-500)',
      marginTop: 'var(--space-sm)'
    }
  }, disclaimer) : null, gpxHref ? /*#__PURE__*/React.createElement("a", {
    href: gpxHref,
    download: true,
    style: {
      marginTop: 'auto',
      display: 'inline-flex',
      alignItems: 'center',
      gap: 'var(--space-xs)',
      alignSelf: 'flex-start',
      padding: '0.75rem 1.5rem',
      background: 'var(--white)',
      color: 'var(--gray-900)',
      boxShadow: 'var(--shadow-sm)',
      borderRadius: '6px',
      fontWeight: 'var(--weight-semibold)',
      fontSize: '0.9rem',
      textDecoration: 'none'
    }
  }, "GPX Download") : null));
}
Object.assign(__ds_scope, { RouteCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/RouteCard.jsx", error: String((e && e.message) || e) }); }

// components/site/RunCard.jsx
try { (() => {
function RunCard({
  category,
  distance,
  description,
  href = '#strecke'
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", {
    href: href,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--white)',
      border: '1px solid ' + (hover ? 'var(--color-primary)' : 'var(--gray-200)'),
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-lg)',
      display: 'flex',
      flexDirection: 'column',
      boxShadow: hover ? 'var(--shadow-md)' : 'var(--shadow-sm)',
      transform: hover ? 'var(--lift-card)' : 'none',
      transition: 'all var(--duration-med) var(--ease)',
      textDecoration: 'none',
      color: 'inherit'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      marginBottom: 'var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-main)',
      fontSize: '0.875rem',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--color-primary)',
      textTransform: 'uppercase'
    }
  }, category), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-heading)',
      fontSize: '1.5rem',
      fontWeight: 'var(--weight-black)',
      color: 'var(--gray-900)'
    }
  }, distance)), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-main)',
      color: 'var(--gray-600)',
      margin: 0,
      flexGrow: 1,
      lineHeight: 'var(--leading-body)'
    }
  }, description));
}
Object.assign(__ds_scope, { RunCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/RunCard.jsx", error: String((e && e.message) || e) }); }

// components/site/SectionHeading.jsx
try { (() => {
function SectionHeading({
  children,
  subtitle,
  rule = false,
  align = 'center',
  style
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: align,
      ...style
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontFamily: 'var(--font-heading)',
      fontWeight: 'var(--weight-bold)',
      fontSize: 'var(--text-h2)',
      lineHeight: 'var(--leading-tight)',
      color: 'var(--text-strong)',
      margin: 0
    }
  }, children), rule ? /*#__PURE__*/React.createElement("div", {
    style: {
      width: '64px',
      height: '4px',
      background: 'var(--color-primary)',
      borderRadius: '2px',
      margin: align === 'center' ? '0.9rem auto 0' : '0.9rem 0 0'
    }
  }) : null, subtitle ? /*#__PURE__*/React.createElement("p", {
    style: {
      maxWidth: '680px',
      margin: align === 'center' ? '1rem auto 0' : '1rem 0 0',
      color: 'var(--gray-600)',
      fontFamily: 'var(--font-main)'
    }
  }, subtitle) : null);
}
Object.assign(__ds_scope, { SectionHeading });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/SectionHeading.jsx", error: String((e && e.message) || e) }); }

// components/site/SponsorMarquee.jsx
try { (() => {
function SponsorMarquee({
  logos = [],
  speed = 28
}) {
  const doubled = logos.concat(logos);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: 'calc(100% - 88px)',
      maxWidth: 'var(--marquee-max)',
      margin: 'var(--space-lg) auto 0',
      overflow: 'hidden',
      WebkitMaskImage: 'linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%)',
      maskImage: 'linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%)'
    }
  }, /*#__PURE__*/React.createElement("style", null, '@keyframes ml-sponsor-marquee{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}'), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '3rem',
      width: 'max-content',
      animation: 'ml-sponsor-marquee ' + speed + 's linear infinite'
    }
  }, doubled.map((l, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      flex: '0 0 240px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: l.src,
    alt: l.alt || '',
    style: {
      width: '210px',
      height: '105px',
      objectFit: 'contain',
      userSelect: 'none'
    }
  })))));
}
Object.assign(__ds_scope, { SponsorMarquee });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/SponsorMarquee.jsx", error: String((e && e.message) || e) }); }

// components/site/TimelineItem.jsx
try { (() => {
function TimelineItem({
  time,
  event,
  note,
  last = false
}) {
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      paddingLeft: '40px',
      marginBottom: last ? 0 : 'var(--space-lg)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      left: 0,
      top: '4px',
      width: '18px',
      height: '18px',
      background: 'var(--color-primary)',
      border: '3px solid var(--white)',
      borderRadius: '50%',
      boxShadow: '0 0 0 2px var(--color-primary)',
      zIndex: 2
    }
  }), /*#__PURE__*/React.createElement("div", {
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--white)',
      padding: 'var(--space-md)',
      borderRadius: 'var(--radius-lg)',
      border: '1px solid var(--gray-200)',
      boxShadow: hover ? 'var(--shadow-lg)' : 'var(--shadow-md)',
      transform: hover ? 'translateY(-4px)' : 'none',
      transition: 'all var(--duration-med) var(--ease)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'block',
      marginBottom: 'var(--space-sm)',
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--gray-500)',
      fontSize: '0.875rem',
      textTransform: 'uppercase',
      letterSpacing: 'var(--tracking-label)'
    }
  }, time), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'block',
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-bold)',
      color: 'var(--gray-800)',
      fontSize: '1.25rem'
    }
  }, event), note ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'block',
      fontSize: '0.85rem',
      color: '#6c757d',
      marginTop: '-4px'
    }
  }, note) : null));
}
Object.assign(__ds_scope, { TimelineItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/TimelineItem.jsx", error: String((e && e.message) || e) }); }

// ui_kits/orga_dashboard/Cockpit.jsx
try { (() => {
const {
  KpiTile,
  Kachel,
  Button
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
function Cockpit({
  onSelect
}) {
  const tiles = window.ORGA_NAV.filter(i => i.tile !== false && i.key !== 'dashboard');
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("header", {
    style: {
      marginBottom: '1.25rem'
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: '1.35rem',
      color: 'var(--orga-text)',
      margin: 0,
      fontFamily: 'var(--font-ui)',
      fontWeight: 600
    }
  }, "Cockpit")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
      gap: '1rem',
      marginBottom: '1.5rem'
    }
  }, tiles.map(t => /*#__PURE__*/React.createElement(KpiTile, {
    key: t.key,
    title: t.label,
    value: t.value,
    label: t.kpiLabel,
    signal: t.signal || 'neutral',
    href: "#",
    note: t.value === undefined ? 'Werkzeug ohne Kennzahl' : undefined
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
      gap: '1rem'
    }
  }, /*#__PURE__*/React.createElement(Kachel, {
    title: "Offene Aufgaben"
  }, /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      margin: 0,
      padding: 0
    }
  }, [['Streckenfreigabe Gemeinde nachfassen', '18.08.'], ['Sponsorenmappe an KSK senden', '22.08.'], ['Verpflegungsstand: 2 Helfer fehlen', '01.09.']].map(([t, d]) => /*#__PURE__*/React.createElement("li", {
    key: t,
    style: {
      padding: '0.5rem 0',
      borderBottom: '1px solid var(--orga-border)',
      display: 'flex',
      justifyContent: 'space-between',
      gap: '0.5rem',
      fontSize: '0.9rem'
    }
  }, /*#__PURE__*/React.createElement("span", null, t), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--orga-text-light)',
      whiteSpace: 'nowrap'
    }
  }, d))))), /*#__PURE__*/React.createElement(Kachel, {
    title: "Schnellzugriff"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: '0.5rem'
    }
  }, [['Race Result', 'var(--brand-raceresult)'], ['Trello', 'var(--brand-trello)'], ['OneDrive', 'var(--brand-onedrive)'], ['GitHub', 'var(--brand-github)'], ['Strava', 'var(--brand-strava)']].map(([l, c]) => /*#__PURE__*/React.createElement("a", {
    key: l,
    href: "#",
    style: {
      display: 'inline-block',
      padding: '0.35rem 0.7rem',
      borderRadius: 'var(--radius-orga)',
      color: '#fff',
      background: c,
      fontSize: '0.85rem',
      fontWeight: 500,
      textDecoration: 'none'
    }
  }, l)))), /*#__PURE__*/React.createElement(Kachel, {
    title: "Live-Ticker",
    actions: /*#__PURE__*/React.createElement(Button, {
      variant: "primary",
      size: "sm",
      onClick: () => onSelect('live_ticker')
    }, "Bearbeiten")
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: '0.9rem'
    }
  }, "\u201EAnmeldung ist er\xF6ffnet \u2014 Startpl\xE4tze sind begrenzt.\""), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: '0.75rem',
      color: 'var(--orga-text-light)',
      marginTop: '0.35rem'
    }
  }, "aktiv seit 04.08.2026 \xB7 Typ: Info"))));
}
Object.assign(window, {
  Cockpit
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/orga_dashboard/Cockpit.jsx", error: String((e && e.message) || e) }); }

// ui_kits/orga_dashboard/HelferListe.jsx
try { (() => {
const {
  DataTable,
  Badge,
  Button,
  FormField,
  Alert
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
const HELFER = [['Anna Bauer', 'anna.bauer@example.de', '0170 1234567', 'bestaetigt', 'Ja', 'Verpflegung Ziel', '02.08.2026 18:41'], ['Michael Huber', 'm.huber@example.de', '0151 9876543', 'neu', 'Nein', '-', '03.08.2026 09:12'], ['Sabine Wagner', 's.wagner@example.de', '08091 12345', 'bestaetigt', 'Ja', 'Streckenposten km 3', '03.08.2026 20:05'], ['Tobias Lindner', 't.lindner@example.de', '0176 4455667', 'neu', 'Ja', '-', '04.08.2026 07:58'], ['Petra Moser', 'p.moser@example.de', '0170 7788990', 'abgelehnt', 'Nein', '-', '04.08.2026 11:30']];
const LABEL = {
  neu: 'Neu',
  bestaetigt: 'Bestätigt',
  abgelehnt: 'Abgelehnt'
};
function HelferListe() {
  const [filter, setFilter] = React.useState('');
  const [flash, setFlash] = React.useState('');
  const rows = HELFER.filter(h => !filter || h[3] === filter).map(h => [/*#__PURE__*/React.createElement("strong", null, h[0]), /*#__PURE__*/React.createElement("a", {
    href: 'mailto:' + h[1],
    style: {
      color: 'var(--color-primary)'
    }
  }, h[1]), h[2], /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: '0.25rem',
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    tone: h[3]
  }, LABEL[h[3]]), h[3] === 'neu' ? /*#__PURE__*/React.createElement("button", {
    onClick: () => setFlash('Zugangslink an ' + h[0] + ' gesendet.'),
    style: {
      padding: '4px 10px',
      fontSize: '0.75rem',
      background: '#28a745',
      color: '#fff',
      border: 'none',
      borderRadius: '4px',
      cursor: 'pointer',
      whiteSpace: 'nowrap'
    }
  }, "Best\xE4tigen + Mail") : null), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-block',
      padding: '0.15rem 0.5rem',
      borderRadius: '4px',
      fontSize: '0.75rem',
      fontWeight: 600,
      background: h[4] === 'Ja' ? 'var(--success-bg)' : 'var(--error-bg)',
      color: h[4] === 'Ja' ? 'var(--success-fg)' : 'var(--error)'
    }
  }, h[4]), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.75rem',
      color: 'var(--orga-text-light)'
    }
  }, h[5]), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.75rem',
      color: 'var(--orga-text-light)'
    }
  }, h[6]), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: '0.25rem'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      padding: '0.25rem 0.5rem',
      fontSize: '0.75rem',
      background: 'var(--white)',
      color: 'var(--orga-text)',
      border: '1px solid var(--orga-border)',
      borderRadius: '4px',
      textAlign: 'center',
      textDecoration: 'none'
    }
  }, "Bearbeiten"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      padding: '0.25rem 0.5rem',
      fontSize: '0.75rem',
      background: 'var(--white)',
      color: 'var(--orga-text)',
      border: '1px solid var(--orga-border)',
      borderRadius: '4px',
      textAlign: 'center',
      textDecoration: 'none'
    }
  }, "Briefing"))]);
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("header", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '1.25rem',
      flexWrap: 'wrap',
      marginBottom: '1.25rem'
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: '1.35rem',
      color: 'var(--orga-text)',
      margin: 0,
      fontFamily: 'var(--font-ui)',
      fontWeight: 600
    }
  }, "Helfer-\xDCbersicht"), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    size: "sm"
  }, "Maske Helfereinladung \u2197"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    size: "sm",
    style: {
      border: '1px solid var(--orga-border)',
      color: 'var(--orga-text)'
    }
  }, "Briefing-\xDCbersicht \u2197")), flash ? /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: '1rem'
    }
  }, /*#__PURE__*/React.createElement(Alert, {
    tone: "success"
  }, flash)) : null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '1rem',
      alignItems: 'flex-end',
      marginBottom: '1.5rem',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: '170px'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Status",
    type: "select",
    name: "f-status",
    dense: true,
    placeholder: "Alle",
    options: ['neu', 'bestaetigt', 'abgelehnt'],
    value: filter,
    onChange: e => setFilter(e.target.value)
  })), filter ? /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    size: "sm",
    style: {
      border: '1px solid var(--orga-border)',
      marginBottom: '1rem'
    },
    onClick: () => setFilter('')
  }, "Filter zur\xFCcksetzen") : null), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: '0.875rem',
      color: 'var(--orga-text-light)',
      marginBottom: '1rem'
    }
  }, rows.length, " von ", HELFER.length, " Helfern angezeigt"), /*#__PURE__*/React.createElement(DataTable, {
    columns: ['Name', 'E-Mail', 'Telefon', 'Status', 'Fotofreigabe', 'Einsatz', 'Anmeldung', 'Aktion'],
    rows: rows,
    empty: "Keine Helfer gefunden."
  }));
}
Object.assign(window, {
  HelferListe
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/orga_dashboard/HelferListe.jsx", error: String((e && e.message) || e) }); }

// ui_kits/orga_dashboard/Login.jsx
try { (() => {
const {
  Button,
  FormField,
  Alert
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
function Login({
  onLogin
}) {
  const [err, setErr] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      minHeight: '100vh',
      padding: '1rem',
      background: 'var(--orga-bg)',
      fontFamily: 'var(--font-ui)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--white)',
      padding: '2rem',
      borderRadius: '8px',
      boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
      width: '100%',
      maxWidth: '400px'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/images/logo-final.svg",
    alt: "Marktlauf",
    style: {
      height: '54px',
      margin: '0 auto 1rem'
    }
  }), /*#__PURE__*/React.createElement("h1", {
    style: {
      marginBottom: '1.5rem',
      color: 'var(--color-primary)',
      textAlign: 'center',
      fontSize: '1.5rem',
      fontFamily: 'var(--font-ui)'
    }
  }, "Orga-Dashboard"), err ? /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: '1rem'
    }
  }, /*#__PURE__*/React.createElement(Alert, {
    tone: "error"
  }, "E-Mail oder Passwort stimmt nicht.")) : null, /*#__PURE__*/React.createElement(FormField, {
    label: "E-Mail",
    type: "email",
    name: "login-mail",
    dense: true,
    placeholder: "name@atsv-kirchseeon.de"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Passwort",
    type: "text",
    name: "login-pw",
    dense: true,
    placeholder: "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022"
  }), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    block: true,
    onClick: onLogin
  }, "Anmelden"), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: '1.5rem',
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      setErr(true);
    },
    style: {
      color: 'var(--orga-text-light)',
      fontSize: '0.85rem'
    }
  }, "Passwort vergessen?"))));
}
Object.assign(window, {
  Login
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/orga_dashboard/Login.jsx", error: String((e && e.message) || e) }); }

// ui_kits/orga_dashboard/navItems.js
try { (() => {
// Modul-Registry — im Original _nav.php: eine Liste speist Sidebar UND Cockpit-Kacheln.
window.ORGA_NAV = [{
  key: 'dashboard',
  label: 'Cockpit',
  tile: false
}, {
  key: 'helfer',
  label: 'Helfer-Übersicht',
  section: 'HELFER-ORGA',
  value: '42',
  kpiLabel: 'Anmeldungen · 3 neu',
  signal: 'attention'
}, {
  key: 'schichten',
  label: 'Einsatzplan',
  section: 'HELFER-ORGA',
  value: '88/96',
  kpiLabel: 'Plätze besetzt · 8 offen',
  signal: 'attention'
}, {
  key: 'beitraege',
  label: 'Kuchen & Sonstiges',
  section: 'HELFER-ORGA',
  value: '19',
  kpiLabel: 'Kuchen-Zusagen · 4× sonstiges',
  signal: 'neutral'
}, {
  key: 'helfer_draht',
  label: 'Helfer-Draht',
  section: 'HELFER-ORGA',
  value: '36',
  kpiLabel: 'erreichbare Helfer',
  signal: 'neutral'
}, {
  key: 'sponsoren',
  label: 'Sponsoren',
  section: 'SPONSOREN-HANDLING',
  value: '17',
  kpiLabel: 'Sponsoren · 4.200 € zugesagt',
  signal: 'neutral'
}, {
  key: 'sponsor_briefe',
  label: 'Sponsorenbriefe',
  section: 'SPONSOREN-HANDLING',
  value: '0',
  kpiLabel: 'offen in Versand-Queue',
  signal: 'ok'
}, {
  key: 'rechnungen',
  label: 'Rechnungen',
  section: 'SPONSOREN-HANDLING',
  value: '2',
  kpiLabel: 'Entwürfe ohne Nummer',
  signal: 'attention'
}, {
  key: 'vereine',
  label: 'Vereine & Laufevents',
  section: 'VEREINE & LAUFEVENTS',
  value: '54',
  kpiLabel: 'Kontakte · 21 Laufevents',
  signal: 'neutral'
}, {
  key: 'vereine_briefe',
  label: 'Vereins-Anschreiben',
  section: 'VEREINE & LAUFEVENTS',
  value: '0',
  kpiLabel: 'offen in Versand-Queue',
  signal: 'ok'
}, {
  key: 'social_media',
  label: 'Social-Media',
  section: 'KOMMUNIKATION',
  value: '3',
  kpiLabel: 'Entwürfe',
  signal: 'attention'
}, {
  key: 'vorlagen',
  label: 'Grafik-Vorlagen',
  section: 'KOMMUNIKATION'
}, {
  key: 'live_ticker',
  label: 'Live-Ticker',
  section: 'KOMMUNIKATION',
  value: '1',
  kpiLabel: 'aktive Meldung',
  signal: 'ok'
}, {
  key: 'dateien',
  label: 'Dateien',
  section: 'ABLAGE',
  value: '128',
  kpiLabel: 'Dateien abgelegt',
  signal: 'neutral'
}, {
  key: 'prompt_bibliothek',
  label: 'Prompt-Bibliothek',
  section: 'ABLAGE',
  value: '11',
  kpiLabel: 'Prompts gespeichert',
  signal: 'neutral'
}, {
  key: 'benutzer',
  label: 'Benutzerverwaltung',
  section: 'ADMIN',
  tile: false
}, {
  key: 'ci',
  label: 'CI & Design',
  section: 'ADMIN'
}, {
  key: 'einstellungen',
  label: 'Einstellungen',
  section: 'ADMIN',
  tile: false
}];
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/orga_dashboard/navItems.js", error: String((e && e.message) || e) }); }

// ui_kits/website/Connect.jsx
try { (() => {
const {
  SectionHeading,
  Tabs,
  FormField,
  Checkbox,
  Button,
  Alert
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
const container = {
  width: '100%',
  maxWidth: 'var(--container-max)',
  margin: '0 auto',
  padding: '0 var(--space-md)'
};
function Anmeldung() {
  return /*#__PURE__*/React.createElement("section", {
    id: "anmeldung",
    style: {
      padding: 'var(--space-xl) 0',
      background: 'var(--gray-50)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, {
    subtitle: "Sichere dir jetzt deinen Startplatz f\xFCr den Marktlauf 2026."
  }, "Anmeldung"), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '800px',
      margin: 'var(--space-lg) auto 0'
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    tabs: [{
      id: 'einzel',
      label: 'Einzelanmeldung'
    }, {
      id: 'sammel',
      label: 'Familie / mehrere Personen'
    }]
  }, id => id === 'einzel' ? /*#__PURE__*/React.createElement(RegForm, null) : /*#__PURE__*/React.createElement(SammelForm, null)))));
}
function RegForm() {
  const steps = ['Person', 'Lauf', 'Kontakt', 'Prüfen', 'Bezahlen'];
  const [step, setStep] = React.useState(0);
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      borderRadius: 'var(--radius-sm)',
      overflow: 'hidden',
      marginBottom: 'var(--space-md)'
    }
  }, steps.map((s, i) => /*#__PURE__*/React.createElement("div", {
    key: s,
    onClick: () => setStep(i),
    style: {
      flex: 1,
      textAlign: 'center',
      padding: '10px 6px',
      fontFamily: 'var(--font-main)',
      fontSize: '13px',
      fontWeight: 'var(--weight-semibold)',
      letterSpacing: '0.3px',
      cursor: 'pointer',
      color: 'var(--white)',
      background: i === step ? 'var(--color-primary-dark)' : 'var(--color-primary)'
    }
  }, s))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: '0 var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Vorname",
    name: "rr-vorname",
    placeholder: "Vorname",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachname",
    name: "rr-nachname",
    placeholder: "Nachname",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Geburtsdatum",
    type: "date",
    name: "rr-geb",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Lauf",
    type: "select",
    name: "rr-lauf",
    placeholder: "Distanz w\xE4hlen",
    options: ['Bambini 500 m', 'Schüler 1 km', 'Schüler 2 km', '5 km', '10 km']
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "E-Mail",
    type: "email",
    name: "rr-mail",
    placeholder: "ihre@email.de",
    required: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 'var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    name: "rr-agb",
    required: true
  }, "Ich akzeptiere die Teilnahmebedingungen des ATSV Kirchseeon 1906 e. V.")), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    block: true,
    onClick: () => setStep(Math.min(step + 1, steps.length - 1))
  }, "Weiter"));
}
function SammelForm() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-main)',
      color: 'var(--gray-600)',
      marginTop: 0
    }
  }, "F\xFCr Familien und Gruppen: alle Teilnehmenden in einem Vorgang anmelden."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr 1fr',
      gap: '0 var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Vorname",
    name: "s1v",
    placeholder: "Vorname"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachname",
    name: "s1n",
    placeholder: "Nachname"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Lauf",
    type: "select",
    name: "s1l",
    placeholder: "Distanz",
    options: ['Bambini 500 m', '1 km', '2 km', '5 km', '10 km']
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Vorname",
    name: "s2v",
    placeholder: "Vorname"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachname",
    name: "s2n",
    placeholder: "Nachname"
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Lauf",
    type: "select",
    name: "s2l",
    placeholder: "Distanz",
    options: ['Bambini 500 m', '1 km', '2 km', '5 km', '10 km']
  })), /*#__PURE__*/React.createElement(Button, {
    variant: "outline",
    pill: true,
    style: {
      marginBottom: 'var(--space-md)'
    }
  }, "+ Person hinzuf\xFCgen"), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    block: true
  }, "Sammelanmeldung absenden"));
}
function Connect() {
  const [sent, setSent] = React.useState(false);
  return /*#__PURE__*/React.createElement("section", {
    id: "connect",
    style: {
      padding: 'var(--space-xl) 0',
      background: 'var(--gray-50)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, null, "Bleiben Sie in Kontakt"), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '560px',
      margin: 'var(--space-lg) auto 0'
    }
  }, /*#__PURE__*/React.createElement(Tabs, {
    tabs: [{
      id: 'newsletter',
      label: 'Newsletter'
    }, {
      id: 'kontakt',
      label: 'Kontakt'
    }]
  }, id => id === 'newsletter' ? sent ? /*#__PURE__*/React.createElement(Alert, {
    tone: "formSuccess"
  }, /*#__PURE__*/React.createElement("strong", null, "Vielen Dank!"), /*#__PURE__*/React.createElement("br", null), "Bitte best\xE4tige deine E-Mail-Adresse \xFCber den Link in der Best\xE4tigungsmail.") : /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      fontFamily: 'var(--font-heading)',
      marginBottom: 'var(--space-sm)'
    }
  }, "Bleib auf dem Laufenden!"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-main)',
      color: 'var(--gray-600)',
      marginBottom: 'var(--space-lg)'
    }
  }, "Melde dich an, um Infos zum Vorverkaufsstart und Strecken-Updates zu erhalten."), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '400px',
      margin: '0 auto',
      textAlign: 'left'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: '0 1rem'
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Vorname",
    name: "nl-v",
    placeholder: "Vorname",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachname",
    name: "nl-n",
    placeholder: "Nachname",
    required: true
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "E-Mail",
    type: "email",
    name: "nl-m",
    placeholder: "ihre@email.de",
    required: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 'var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    name: "nl-ds",
    required: true
  }, "Ich stimme zu, den Marktlauf-Newsletter zu erhalten und akzeptiere die ", /*#__PURE__*/React.createElement("a", {
    href: "#"
  }, "Datenschutzbestimmungen"), ".")), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    block: true,
    onClick: () => setSent(true)
  }, "Newsletter abonnieren"))) : /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(FormField, {
    label: "Vorname",
    name: "k-v",
    placeholder: "Vorname",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachname",
    name: "k-n",
    placeholder: "Nachname",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "E-Mail-Adresse",
    type: "email",
    name: "k-m",
    placeholder: "ihre@email.de",
    required: true
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Betreff / Anliegen",
    type: "select",
    name: "k-b",
    placeholder: "Bitte w\xE4hlen Sie ein Thema aus",
    options: ['Fragen zur Anmeldung / Startgebühr', 'Streckenverlauf & Zeitnahme', 'Sponsoring & Partner', 'Helfer / Volunteers', 'Sonstiges']
  }), /*#__PURE__*/React.createElement(FormField, {
    label: "Nachricht",
    type: "textarea",
    name: "k-t",
    placeholder: "Wie k\xF6nnen wir Ihnen helfen?",
    required: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 'var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    name: "k-ds",
    required: true
  }, "Ich habe die ", /*#__PURE__*/React.createElement("a", {
    href: "#"
  }, "Datenschutzerkl\xE4rung"), " gelesen und stimme der Verarbeitung meiner Daten zu.")), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    block: true
  }, "Nachricht senden"))))));
}
Object.assign(window, {
  Anmeldung,
  Connect
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Connect.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Footer.jsx
try { (() => {
function SiteFooter() {
  const social = [{
    label: 'Instagram',
    bg: 'radial-gradient(circle at 30% 110%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285aeb 90%)'
  }, {
    label: 'Facebook',
    bg: 'var(--brand-facebook)'
  }, {
    label: 'Strava',
    bg: 'var(--brand-strava)'
  }];
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      background: 'var(--gray-900)',
      color: 'var(--gray-300)',
      padding: 'var(--space-xl) 0 var(--space-md)',
      fontFamily: 'var(--font-main)',
      fontSize: '0.875rem'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      maxWidth: 'var(--container-max)',
      margin: '0 auto',
      padding: '0 var(--space-md)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
      gap: 'var(--space-lg)',
      marginBottom: 'var(--space-xl)'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h4", {
    style: {
      color: 'var(--white)',
      marginBottom: 'var(--space-md)',
      fontSize: '1.1rem',
      fontFamily: 'var(--font-heading)'
    }
  }, "Marktlauf 2026"), /*#__PURE__*/React.createElement("p", {
    style: {
      color: 'var(--gray-400)',
      lineHeight: 1.6,
      margin: 0
    }
  }, "Ein sportliches Highlight in Kirchseeon f\xFCr die ganze Familie. Wir freuen uns auf euch!")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h4", {
    style: {
      color: 'var(--white)',
      marginBottom: 'var(--space-md)',
      fontSize: '1.1rem',
      fontFamily: 'var(--font-heading)'
    }
  }, "Schnellzugriff"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-sm)'
    }
  }, ['Läufe', 'Zeitplan', 'Strecke', 'Anmeldung'].map(l => /*#__PURE__*/React.createElement("a", {
    key: l,
    href: "#",
    style: {
      color: 'var(--gray-400)',
      textDecoration: 'none'
    }
  }, l)))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h4", {
    style: {
      color: 'var(--white)',
      marginBottom: 'var(--space-md)',
      fontSize: '1.1rem',
      fontFamily: 'var(--font-heading)'
    }
  }, "Folgen"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-sm)'
    }
  }, social.map(s => /*#__PURE__*/React.createElement("span", {
    key: s.label,
    title: s.label,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      width: '46px',
      height: '46px',
      borderRadius: '12px',
      color: '#fff',
      background: s.bg,
      fontSize: '12px',
      fontWeight: 700
    }
  }, s.label[0]))))), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      color: 'var(--gray-500)'
    }
  }, /*#__PURE__*/React.createElement("p", {
    style: {
      marginBottom: 'var(--space-sm)'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: 'var(--gray-400)',
      textDecoration: 'none'
    }
  }, "Impressum"), /*#__PURE__*/React.createElement("span", {
    style: {
      margin: '0 var(--space-sm)',
      color: 'var(--gray-600)'
    }
  }, "|"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: 'var(--gray-400)',
      textDecoration: 'none'
    }
  }, "Datenschutzerkl\xE4rung")), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0
    }
  }, "\xA9 2026 ATSV Kirchseeon e.V. | Alle Rechte vorbehalten."))));
}
Object.assign(window, {
  SiteFooter
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Footer.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Header.jsx
try { (() => {
const {
  LogoPlakette
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
function SiteHeader({
  scrolled,
  onNav
}) {
  const links = [['distanzen', 'Läufe'], ['ablauf', 'Zeitplan'], ['strecke', 'Strecke'], ['sponsoren', 'Sponsoren'], ['connect', 'Newsletter & Kontakt']];
  return /*#__PURE__*/React.createElement("header", {
    style: {
      position: 'sticky',
      top: 0,
      zIndex: 1000,
      height: '82px',
      display: 'flex',
      alignItems: 'center',
      background: scrolled ? 'var(--white)' : 'linear-gradient(180deg, rgba(12,60,35,.28), rgba(12,60,35,0))',
      boxShadow: scrolled ? 'var(--shadow-sm)' : 'none',
      transition: 'background .2s ease, box-shadow .2s ease'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: '40px',
      width: '100%',
      maxWidth: 'var(--shell-max)',
      margin: '0 auto',
      padding: '0 var(--shell-pad)'
    }
  }, /*#__PURE__*/React.createElement(LogoPlakette, {
    base: "../../",
    plain: scrolled
  }), /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      gap: '30px',
      alignItems: 'center'
    }
  }, links.map(([id, label]) => /*#__PURE__*/React.createElement("a", {
    key: id,
    href: '#' + id,
    onClick: e => {
      e.preventDefault();
      onNav(id);
    },
    style: {
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-medium)',
      fontSize: '15px',
      textDecoration: 'none',
      color: scrolled ? 'var(--gray-600)' : 'rgba(255,255,255,.92)'
    }
  }, label)), /*#__PURE__*/React.createElement("button", {
    title: "Sprache wechseln",
    style: {
      background: 'none',
      border: 'none',
      padding: 0,
      cursor: 'pointer',
      width: '36px',
      height: '24px'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/images/allemand.png",
    alt: "Deutsch",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'contain'
    }
  })), /*#__PURE__*/React.createElement("a", {
    href: "#anmeldung",
    onClick: e => {
      e.preventDefault();
      onNav('anmeldung');
    },
    style: {
      background: scrolled ? 'var(--color-primary)' : 'var(--color-accent-yellow)',
      color: scrolled ? 'var(--white)' : 'var(--color-ink)',
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-semibold)',
      fontSize: '15px',
      padding: '11px 22px',
      borderRadius: 'var(--radius-pill)',
      textDecoration: 'none'
    }
  }, "Jetzt anmelden"))));
}
Object.assign(window, {
  SiteHeader
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Header.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Hero.jsx
try { (() => {
const {
  KoopBadge,
  Countdown,
  Button
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
function Hero({
  onNav
}) {
  return /*#__PURE__*/React.createElement("section", {
    id: "hero",
    style: {
      position: 'relative',
      minHeight: 'calc(100vh - 82px)',
      display: 'flex',
      flexDirection: 'column',
      background: 'var(--gradient-hero)',
      color: 'var(--white)',
      isolation: 'isolate',
      overflow: 'clip',
      marginTop: '-82px',
      paddingTop: '82px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: '-160px',
      right: '-120px',
      width: '620px',
      height: '620px',
      borderRadius: '50%',
      background: 'radial-gradient(circle at 40% 40%, rgba(244,184,30,.55), rgba(244,184,30,0) 70%)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      bottom: '-220px',
      left: 0,
      width: '640px',
      height: '640px',
      borderRadius: '50%',
      background: 'radial-gradient(circle at 50% 50%, rgba(18,168,119,.7), rgba(18,168,119,0) 70%)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: '120px',
      left: '44%',
      width: '360px',
      height: '360px',
      borderRadius: '50%',
      background: 'radial-gradient(circle, rgba(255,255,255,.16), rgba(255,255,255,0) 68%)',
      pointerEvents: 'none'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      maxWidth: 'var(--shell-max)',
      margin: '0 auto',
      padding: '0 var(--shell-pad)',
      position: 'relative',
      display: 'flex',
      flexGrow: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      zIndex: 3,
      width: '100%',
      maxWidth: '760px',
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      padding: '80px 20px 60px 0'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 0,
      bottom: 0,
      left: '-100vw',
      right: 0,
      background: 'linear-gradient(90deg, rgba(0,0,0,.38) 0%, rgba(0,0,0,.32) 50%, rgba(0,0,0,.18) 80%, transparent 100%)',
      pointerEvents: 'none',
      zIndex: -1
    }
  }), /*#__PURE__*/React.createElement(KoopBadge, {
    base: "../../",
    style: {
      marginBottom: '26px',
      alignSelf: 'flex-start'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '12px',
      marginBottom: '14px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: '34px',
      height: '3px',
      background: 'var(--color-accent-yellow)',
      borderRadius: '2px'
    }
  }), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '5px',
      color: 'inherit',
      textDecoration: 'none'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-semibold)',
      letterSpacing: 'var(--tracking-kicker)',
      textTransform: 'uppercase',
      fontSize: '16px',
      whiteSpace: 'nowrap',
      color: '#fff8dd'
    }
  }, "Energie- & Umwelttag 2026"), /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: "13",
    height: "13",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    style: {
      opacity: .75
    }
  }, /*#__PURE__*/React.createElement("path", {
    d: "M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"
  }), /*#__PURE__*/React.createElement("polyline", {
    points: "15 3 21 3 21 9"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "10",
    y1: "14",
    x2: "21",
    y2: "3"
  })))), /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 'var(--weight-bold)',
      color: 'var(--white)',
      fontSize: 'var(--text-hero)',
      lineHeight: 'var(--leading-hero)',
      letterSpacing: 'var(--tracking-hero)',
      textShadow: '0 6px 24px rgba(20,60,30,.28)'
    }
  }, "Marktlauf", /*#__PURE__*/React.createElement("br", null), "Kirchseeon"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: '16px',
      margin: '22px 0 8px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 'var(--weight-bold)',
      fontSize: '40px',
      color: 'var(--color-accent-yellow)',
      WebkitTextStroke: '1px rgba(255,255,255,.35)'
    }
  }, "2026"), /*#__PURE__*/React.createElement("span", {
    style: {
      background: 'var(--white)',
      color: 'var(--color-deep-green)',
      fontFamily: 'var(--font-display)',
      fontWeight: 'var(--weight-semibold)',
      fontSize: '23px',
      padding: '8px 18px',
      borderRadius: 'var(--radius-md)',
      transform: 'rotate(-2deg)',
      boxShadow: '0 6px 16px rgba(0,0,0,.14)'
    }
  }, "E-Mobilit\xE4t & Sport")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: '7px',
      marginTop: '24px',
      fontFamily: 'var(--font-main)',
      fontWeight: 'var(--weight-medium)',
      fontSize: '18px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: '2px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '9px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: '9px',
      height: '9px',
      borderRadius: '50%',
      background: 'var(--color-accent-yellow)',
      flexShrink: 0
    }
  }), /*#__PURE__*/React.createElement("span", null, "Sonntag, 20. September 2026 \xB7 Start 10:00 Uhr")), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.72em',
      opacity: .82,
      paddingLeft: '18px'
    }
  }, "(Startnummern-Ausgabe vorauss. ab 09:00 Uhr)")), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '9px'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: '9px',
      height: '9px',
      borderRadius: '50%',
      background: 'var(--white)',
      flexShrink: 0
    }
  }), /*#__PURE__*/React.createElement("span", null, "Start & Ziel: JEK, Westring 6, 85614 Kirchseeon"))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: '24px'
    }
  }, /*#__PURE__*/React.createElement(Countdown, null)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: '16px',
      marginTop: '34px',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "gold",
    size: "lg",
    pill: true,
    onClick: () => onNav('anmeldung')
  }, "Jetzt anmelden"), /*#__PURE__*/React.createElement(Button, {
    variant: "onGradient",
    size: "lg",
    pill: true,
    onClick: () => onNav('distanzen')
  }, "Alle L\xE4ufe"))), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: '130px',
      right: 'var(--shell-pad)',
      width: '92px',
      height: '92px',
      background: 'var(--white)',
      borderRadius: '50%',
      padding: '14px',
      boxShadow: '0 14px 30px -8px rgba(0,0,0,.3)',
      zIndex: 3
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/images/logo-final.svg",
    alt: "Marktlauf Logo",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'contain'
    }
  })), /*#__PURE__*/React.createElement("img", {
    src: "../../assets/images/laeufer.png",
    alt: "L\xE4ufer",
    style: {
      position: 'absolute',
      bottom: 0,
      right: '5%',
      height: '80%',
      width: 'auto',
      zIndex: 2,
      pointerEvents: 'none'
    }
  })));
}
Object.assign(window, {
  Hero
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Hero.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Sections.jsx
try { (() => {
const {
  SectionHeading,
  RunCard,
  TimelineItem,
  RouteCard,
  SponsorMarquee
} = window.MarktlaufKirchseeonDesignSystem_48e1b5;
const sectionPad = {
  padding: 'var(--space-xl) 0'
};
const container = {
  width: '100%',
  maxWidth: 'var(--container-max)',
  margin: '0 auto',
  padding: '0 var(--space-md)'
};
function Ueber() {
  return /*#__PURE__*/React.createElement("section", {
    id: "ueber",
    style: {
      ...sectionPad,
      background: 'var(--gray-50)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: '760px',
      margin: '0 auto',
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement(SectionHeading, {
    rule: true
  }, "\xDCber den Marktlauf Kirchseeon"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-main)',
      fontSize: '1.2rem',
      fontWeight: 'var(--weight-medium)',
      lineHeight: 1.7,
      color: 'var(--gray-800)',
      margin: '1.5rem 0'
    }
  }, "Der Marktlauf Kirchseeon ist das j\xE4hrliche Laufevent des ATSV Kirchseeon 1906 e. V. Jedes Jahr im September verwandelt sich das Kirchseeoner Umland in eine Laufstrecke f\xFCr alle Generationen \u2014 vom Bambini-Lauf \xFCber die Sch\xFClerl\xE4ufe bis zur 10-km-Distanz."), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-main)',
      textAlign: 'left',
      lineHeight: 'var(--leading-prose)',
      color: 'var(--gray-600)',
      margin: 0
    }
  }, "Der Marktlauf ist mehr als ein Wettkampf: Als Teil des Energie- & Umwelttags verbindet er Sport, Familie und Nachhaltigkeit zu einem Fest f\xFCr die ganze Region. Vereine und lokale Sponsoren sorgen f\xFCr Stimmung und Verpflegung, im Ziel warten Urkunden und Medaillen auf alle Finisher."))));
}
function Distanzen() {
  return /*#__PURE__*/React.createElement("section", {
    id: "distanzen",
    style: {
      ...sectionPad,
      background: 'var(--white)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, null, "Die L\xE4ufe"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
      gap: 'var(--space-lg)',
      marginTop: 'var(--space-lg)'
    }
  }, /*#__PURE__*/React.createElement(RunCard, {
    category: "Bambini",
    distance: "500m",
    description: "Der perfekte Einstieg f\xFCr die kleinsten Sportler. Ein kurzer, spannender Lauf f\xFCr alle Bambinis."
  }), /*#__PURE__*/React.createElement(RunCard, {
    category: "Sch\xFCler",
    distance: "1km & 2km",
    description: "Herausforderungen f\xFCr Sch\xFCler in zwei verschiedenen Distanzen, um die eigenen Grenzen zu testen."
  }), /*#__PURE__*/React.createElement(RunCard, {
    category: "Jugend & Erwachsene",
    distance: "5km & 10km",
    description: "Die K\xF6nigsdisziplinen f\xFCr ambitionierte L\xE4ufer und Hobbysportler. Wer holt sich den Sieg in Kirchseeon?"
  }))));
}
function Zeitplan() {
  return /*#__PURE__*/React.createElement("section", {
    id: "ablauf",
    style: {
      ...sectionPad,
      background: 'var(--gray-50)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, {
    subtitle: "Zeitplan ist noch vorl\xE4ufig und kann sich noch mal \xE4ndern."
  }, "Zeitplan"), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      maxWidth: '600px',
      margin: 'var(--space-xl) auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      left: '8px',
      top: 0,
      width: '2px',
      height: '100%',
      background: 'var(--gray-200)'
    }
  }), /*#__PURE__*/React.createElement(TimelineItem, {
    time: "10:00 Uhr",
    event: "Bambini \xB7 500 m",
    note: "bis 6 Jahre"
  }), /*#__PURE__*/React.createElement(TimelineItem, {
    time: "10:30 Uhr",
    event: "Sch\xFCler \xB7 1 km & 2 km",
    note: "ab 6 Jahre (1km) | 8\u201312 Jahre (2km)"
  }), /*#__PURE__*/React.createElement(TimelineItem, {
    time: "11:00 Uhr",
    event: "Jugend & Erwachsene \xB7 5 km & 10 km",
    note: "ab 13 Jahre (5km) | ab 16 Jahre (10km)"
  }), /*#__PURE__*/React.createElement(TimelineItem, {
    time: "12:30\u201313:30 Uhr",
    event: "Siegerehrungen",
    last: true
  }))));
}
function Strecke() {
  return /*#__PURE__*/React.createElement("section", {
    id: "strecke",
    style: {
      ...sectionPad,
      background: 'var(--white)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, {
    subtitle: "Start und Ziel befinden sich am Westring 6, 85614 Kirchseeon."
  }, "Start & Ziel"), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      width: '100%',
      height: '450px',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-lg)',
      margin: 'var(--space-lg) 0',
      background: 'var(--gray-100)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/images/marktlauf-foto.jpeg",
    alt: "Start und Ziel",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      background: 'var(--white)',
      borderRadius: '50% 50% 50% 0',
      transform: 'rotate(-45deg)',
      border: '1px solid #d1d5db',
      boxShadow: 'var(--shadow-md)',
      width: '38px',
      height: '38px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      transform: 'rotate(45deg)',
      color: 'var(--color-primary)',
      fontWeight: 700
    }
  }, "\u25C6")))), /*#__PURE__*/React.createElement("h3", {
    style: {
      textAlign: 'center',
      fontFamily: 'var(--font-heading)',
      fontSize: 'var(--text-h3)',
      marginBottom: 'var(--space-md)'
    }
  }, "Die Strecken"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
      gap: 'var(--space-lg)',
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement(RouteCard, {
    title: "Bambini",
    distance: "500m",
    age: "bis 6 Jahre",
    blocked: true,
    blockedText: "Streckendetails folgen in K\xFCrze"
  }), /*#__PURE__*/React.createElement(RouteCard, {
    title: "Sch\xFCler",
    distance: "1km",
    age: "ab 6 Jahre",
    disclaimer: "* Streckenverlauf unter Vorbehalt \u2014 noch in Abstimmung mit Gemeinde",
    gpxHref: "#"
  }), /*#__PURE__*/React.createElement(RouteCard, {
    title: "Sch\xFCler",
    distance: "2km",
    age: "8\u201312 Jahre",
    disclaimer: "* Streckenverlauf unter Vorbehalt \u2014 noch in Abstimmung mit Gemeinde",
    gpxHref: "#"
  }), /*#__PURE__*/React.createElement(RouteCard, {
    title: "Jugend & Erwachsene",
    distance: "5km",
    age: "ab 13 Jahre",
    disclaimer: "* Streckenverlauf unter Vorbehalt \u2014 noch in Abstimmung mit Gemeinde",
    gpxHref: "#"
  }), /*#__PURE__*/React.createElement(RouteCard, {
    title: "Jugend & Erwachsene",
    distance: "10km",
    age: "ab 16 Jahre",
    disclaimer: "* Streckenverlauf unter Vorbehalt \u2014 noch in Abstimmung mit Gemeinde",
    gpxHref: "#"
  }))));
}
function Sponsoren() {
  const logos = [{
    src: '../../assets/images/sponsor-apotheke-st-josef.png',
    alt: 'Apotheke St. Josef'
  }, {
    src: '../../assets/images/platzhalter-sponsoren-logo.png',
    alt: 'Sponsorenlogo'
  }, {
    src: '../../assets/images/platzhalter-sponsoren-logo.png',
    alt: 'Sponsorenlogo'
  }, {
    src: '../../assets/images/platzhalter-sponsoren-logo.png',
    alt: 'Sponsorenlogo'
  }];
  return /*#__PURE__*/React.createElement("section", {
    id: "sponsoren",
    style: {
      ...sectionPad,
      background: 'var(--white)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: container
  }, /*#__PURE__*/React.createElement(SectionHeading, null, "Unsere Sponsoren")), /*#__PURE__*/React.createElement(SponsorMarquee, {
    logos: logos
  }));
}
Object.assign(window, {
  Ueber,
  Distanzen,
  Zeitplan,
  Strecke,
  Sponsoren
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Sections.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Alert = __ds_scope.Alert;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Chip = __ds_scope.Chip;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.FormField = __ds_scope.FormField;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.DataTable = __ds_scope.DataTable;

__ds_ns.Kachel = __ds_scope.Kachel;

__ds_ns.KpiTile = __ds_scope.KpiTile;

__ds_ns.OrgaSidebar = __ds_scope.OrgaSidebar;

__ds_ns.Countdown = __ds_scope.Countdown;

__ds_ns.KoopBadge = __ds_scope.KoopBadge;

__ds_ns.LiveTicker = __ds_scope.LiveTicker;

__ds_ns.LogoPlakette = __ds_scope.LogoPlakette;

__ds_ns.RouteCard = __ds_scope.RouteCard;

__ds_ns.RunCard = __ds_scope.RunCard;

__ds_ns.SectionHeading = __ds_scope.SectionHeading;

__ds_ns.SponsorMarquee = __ds_scope.SponsorMarquee;

__ds_ns.TimelineItem = __ds_scope.TimelineItem;

})();
