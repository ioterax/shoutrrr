.spec.template.spec.containers
| if length == 1
    and ((.[0].command // []) | length) == 0
    and ((.[0].args // []) | length) == 0
  then true
  else error("Web-only startup checks require one container using its image command without overrides")
  end
