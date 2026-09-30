# Only an explicitly pinned, unambiguous production baseline is supported.
# Zero-percent tagged revisions do not identify the production baseline.
[.status.traffic[]? | select((.percent // 0) > 0)]
| if length == 1
    and .[0].percent == 100
    and (.[0].latestRevision // false) == false
    and (.[0].revisionName | type) == "string"
    and (.[0].revisionName | test("^shoutrrr-[a-z0-9-]+$"))
  then .[0].revisionName
  else error("Expected one explicitly pinned Shoutrrr revision receiving 100 percent traffic")
  end
