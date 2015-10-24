// CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: http://codemirror.net/LICENSE

(function(mod) {
  if (typeof exports == "object" && typeof module == "object") // CommonJS
    mod(require("../../lib/codemirror"));
  else if (typeof define == "function" && define.amd) // AMD
    define(["../../lib/codemirror"], mod);
  else // Plain browser env
    mod(CodeMirror);
})(function(CodeMirror) {
  "use strict";

  var Pos = CodeMirror.Pos;

  function getHints(cm, options) {
    var tags = options && options.schemaInfo;
    var quote = (options && options.quoteChar) || '"';
    if (!tags) return;
    var cur = cm.getCursor(), token = cm.getTokenAt(cur);
    if (token.end > cur.ch) {
      token.end = cur.ch;
      token.string = token.string.slice(0, cur.ch - token.start);
    }
    var inner = CodeMirror.innerMode(cm.getMode(), token.state);
    if (inner.mode.name != "xml") return;
    var result = [], replaceToken = false, prefix;
    var tag = /\btag\b/.test(token.type) && !/>$/.test(token.string);
    var tagName = tag && /^\w/.test(token.string), tagStart;
    var tagType = null;

    if (tagName) {
      var before = cm.getLine(cur.line).slice(Math.max(0, token.start - 2), token.start);
      tagType = /<\/$/.test(before) ? "close" : /<$/.test(before) ? "open" : null;
      if (tagType) tagStart = token.start - (tagType == "close" ? 2 : 1);
    } else if (tag && token.string == "<") {
      tagType = "open";
    } else if (tag && token.string == "</") {
      tagType = "close";
    }

    var cx = inner.state.context;

    var localtags = tags;
    var topattrs = tags["!attrs"];

    if (cx && cx.tagName) {
      var nodepath = [cx.tagName];
      var prevnode = cx.prev;
      while (prevnode) {
        nodepath.push(prevnode.tagName);
        prevnode = prevnode.prev;
      }
      for (var i = nodepath.length - 1; i >= 0; i--) {
        if (! localtags[nodepath[i]]) {
          break;
        }

        if (localtags["!attrs"]) {
          topattrs = localtags["!attrs"];
        }

        localtags = localtags[nodepath[i]];
      }
    }

    if (!tag && !inner.state.tagName || tagType) {
      if (tagName)
        prefix = token.string;
      replaceToken = tagType;
      if (tagType != "close") {
        for (var name in localtags) {
          if (localtags.hasOwnProperty(name) && name != "!top" && name != "!attrs" && name != "!value" && (!prefix || name.lastIndexOf(prefix, 0) === 0)) {
            if (localtags[name]["!attrs"]) {
              result.push("<" + name);
            } else {
              if (Object.keys(localtags[name]).length === 1 && localtags[name].hasOwnProperty("!novalue")) {
                result.push("<" + name + "/>");
              } else if (Object.keys(localtags[name]).length === 1 && localtags[name].hasOwnProperty("!value")) {
                result.push("<" + name + ">");
              } else {
                result.push("<" + name + ">");
              }
            }
          }
        }
      }
      if (cx && (!prefix || tagType == "close" && cx.tagName.lastIndexOf(prefix, 0) === 0)) {
        result.push("</" + cx.tagName + ">");
      }
    } else {
      // Attribute completion
      var attrs = localtags && localtags[inner.state.tagName] && localtags[inner.state.tagName]["!attrs"];
      if (!attrs) return;
      if (token.type == "string" || token.string == "=") { // A value
        var before = cm.getRange(Pos(cur.line, Math.max(0, cur.ch - 60)),
                                 Pos(cur.line, token.type == "string" ? token.start : token.end));
        var atName = before.match(/([^\s\u00a0=<>\"\']+)=$/), atValues;
        if (!atName || !attrs.hasOwnProperty(atName[1]) || !(atValues = attrs[atName[1]])) return;
        if (typeof atValues == 'function') atValues = atValues.call(this, cm); // Functions can be used to supply values for autocomplete widget
        if (token.type == "string") {
          prefix = token.string;
          var n = 0;
          if (/['"]/.test(token.string.charAt(0))) {
            quote = token.string.charAt(0);
            prefix = token.string.slice(1);
            n++;
          }
          var len = token.string.length;
          if (/['"]/.test(token.string.charAt(len - 1))) {
            quote = token.string.charAt(len - 1);
            prefix = token.string.substr(n, len - 2);
          }
          replaceToken = true;
        }
        for (var i = 0; i < atValues.length; ++i) if (!prefix || atValues[i].lastIndexOf(prefix, 0) === 0)
          result.push(quote + atValues[i] + quote);
      } else { // An attribute name
        if (token.type == "attribute") {
          prefix = token.string;
          replaceToken = true;
        }
        for (var attr in attrs) if (attrs.hasOwnProperty(attr) && (!prefix || attr.lastIndexOf(prefix, 0) === 0))
          result.push(attr);
      }
    }
    return {
      list: result,
      from: replaceToken ? Pos(cur.line, tagStart == null ? token.start : tagStart) : cur,
      to: replaceToken ? Pos(cur.line, token.end) : cur
    };
  }

  CodeMirror.registerHelper("hint", "xml", getHints);
});
