
//Allow to add a show more button when vertical content is too long.
//Give the class hide-too-long to the container you want to partially hide.


// Thanks to Trevor Nestman; https://stackoverflow.com/questions/5270227/

// Create Variables
var allOSB = [];

function hasReadMoreButton(section) {
  var next = section.nextElementSibling;
  return !!(next && next.classList && next.classList.contains("read-more"));
}

function bindReadMore(button) {
  if (!button || button.dataset.boundReadMore === "1") {
    return;
  }

  button.addEventListener("click", function() {
    revealThis(button);
  }, false);
  button.dataset.boundReadMore = "1";
}

function ensureReadMoreButton(section) {
  if (hasReadMoreButton(section)) {
    bindReadMore(section.nextElementSibling);
    return;
  }

  var el = document.createElement("button");
  el.innerHTML = "Afficher +";
  el.setAttribute("type", "button");
  el.setAttribute("class", "read-more read-more--hidden");
  bindReadMore(el);
  insertAfter(section, el);
}

function refreshTrackedSections(root) {
  var scope = root && typeof root.querySelectorAll === "function" ? root : document;
  var nodes = scope.querySelectorAll(".hide-too-long");

  for (var i = 0; i < nodes.length; i++) {
    var section = nodes[i];
    if (allOSB.indexOf(section) === -1) {
      allOSB.push(section);
    }
    ensureReadMoreButton(section);
  }
}

// show only the necessary read-more buttons
function updateReadMore() {
  if (allOSB.length > 0) {
    for (var i = 0; i < allOSB.length; i++) {
      if (!allOSB[i] || !allOSB[i].isConnected) {
        continue;
      }

      var button = allOSB[i].nextElementSibling;
      if (!button || !button.classList || !button.classList.contains("read-more")) {
        continue;
      }

      var currentMaxHeight = window.getComputedStyle(allOSB[i]).getPropertyValue('max-height');
      currentMaxHeight = parseInt(currentMaxHeight.replace('px', ''), 10);

      if (!Number.isFinite(currentMaxHeight) || currentMaxHeight <= 0) {
        button.className = "read-more read-more--hidden";
        continue;
      }

      if (allOSB[i].scrollHeight > currentMaxHeight + 16) {
        if (allOSB[i].hasAttribute("style")) {
          updateHeight(allOSB[i]);
        }
        button.className = "read-more";
      } else {
        button.className = "read-more read-more--hidden";
      }
    }
  }
}

function revealThis(current) {
  var el = current.previousElementSibling;
  if (el.hasAttribute("style")) {
    current.innerHTML = "Afficher +";
    el.removeAttribute("style");
  } else {
    updateHeight(el);
    current.innerHTML = "Réduire";
  }
}

function updateHeight(el) {
  el.style.maxHeight = el.scrollHeight + "px";
}

// thanks to karim79 for this function
// http://stackoverflow.com/a/4793630/5667951
function insertAfter(referenceNode, newNode) {
  referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function initShowMoreLess(root) {
  refreshTrackedSections(root);
  updateReadMore();
}

window.initShowMoreLess = initShowMoreLess;

window.addEventListener("load", function() {
  initShowMoreLess(document);
});

window.addEventListener("resize", function() {
  updateReadMore();
});
