<?php
namespace BEAR\ApiDoc;

final class Template
{
    /**
     * Base template for all content
     */
    const BASE = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>{% block title %}Welcome!{% endblock %}</title>
    {% block stylesheets %}
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">
    {% endblock %}
</head>
<body>
{% block body %}
    {% block contents %}
        <div class="container">
            {% block content %}
            {% endblock %}
        </div>
    {% endblock %}
{% endblock %}
</body>
</html>
';
    /**
     * Index page content
     */
    const INDEX = '{% extends \'base.html.twig\' %}
{% block title %}{{ rel }}{% endblock %}
{% block content %}

    {% if rel is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">rels</a></li>
            <li class="breadcrumb-item active">{{ rel }}</li>
        </ol>
        {% include \'rel.html.twig\' %}
    {% elseif schema is defined %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.html">API Doc</a></li>
            <li class="breadcrumb-item">schemas</a></li>
            <li class="breadcrumb-item active">{{ schema.id }}</li>
        </ol>
        <h1>{{ schema.id }}</h1>
        {%  include \'schema.table.html.twig\' %}
        <p class="lead"><a href="/schemas/{{ schema.id }}">{{ schema.id }} raw file</a></p>
    {% else %}
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">API Doc</li>
        </ol>
        {% include \'home.html.twig\' %}
    {% endif %}
{% endblock %}
';

    /**
     * Home page content
     */
    const HOME = '
<p>{{ message|nl2br }}</p>

<p><b>Link Relations</b></p>
<ul>
    {% for link in links %}
        <li><a href="{{ link.docUri }}">{{ link.rel }}</a> - {{ link.title}}</li>
    {% endfor %}
</ul>
<p><b>Json Schemas</b></p>
<ul>
    {% for schema in schemas %}
        <li><a href="{{ schema.docHref }}">{{ schema.id }}</a> - {{ schema.schema.title }}</li>
    {% endfor %}
</ul>
';

    /**
     * Relation page content
     */
    const REL = '<h2>{{ href }}</h2>
{% for method_name, method in doc %}
    <hr style="width: 100%; color: grey; height: 1px; background-color:grey;" />
    <h1>{{ method_name }}</h1>
    <p class="lead">{{ method.summary }}</p>
    <h4>Request</h4>
    <table class="table table-bordered">
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Description</th>
            <th>Default</th>
            <th>Required</th>
        </tr>
    {% for param_name, parameters in method.request.parameters %}
        <tr>
            <td>{{ param_name }}</td>
            <td>{{ parameters.type }}</td>
            <td>{{ parameters.description }}</td>
            <td>{{ parameters.default }}</td>
            {% if param_name in method.request.required %}
            <td>Required</td>
            {% else %}
            <td>Optional</td>            
            {% endif %}
        </tr>
    {% endfor %}
    </table>
    <h6>
        <span class="badge badge-default">Required</span>
        <span>{{ method.request.required | join(\', \')}}</span>
    </h6>

    {% if method.schema %}
        <div style="height: 30px"></div>
        <h4>Response </h4>
    {%  endif %}
    {%  set meta = method.meta%}
    {%  set schema = method.schema%}
    {%  include \'schema.table.html.twig\' %}
{% endfor %}
';
    /**
     * Schema property table
     */
    const SCHEMA_TABLE = '{% if schema.properties %}
<table class="table table-bordered">
    <tr>
        <th>Property</th>
        <th>Type</th>
        <th>Description</th>
        <th>Required</th>
        <th>Constraints</th>
    </tr>
    {% for prop_name, prop in schema.properties %}
        {% set constrain_num = attribute(meta.constrainNum, prop_name) %}
        <tr>
            <td rowspan="{{ constrain_num }}">{{ prop_name }}</td>
            {% if prop.type is iterable %}
            <td rowspan="{{ constrain_num }}">{{ prop.type | join(\', \') }}</td>
            {% else %}
            <td rowspan="{{ constrain_num }}">{{ prop.type }}</td>
            {% endif %}
            <td rowspan="{{ constrain_num }}">{{ prop.description }}</td>
            {% if prop_name in schema.required %}
                <td rowspan="{{ constrain_num }}">Required</td>
                {% else %}
                <td rowspan="{{ constrain_num }}">Optional</td>            
            {% endif %}
            {% for const_name, const_val in attribute(meta.constatins, prop_name).first %}
                <td>{{ const_name }}: {{ const_val }}</td>
            {% else %}
                <td> n/a </td>
            {% endfor %}

            {%if attribute(meta.constatins, prop_name).extra %}
            <tr>
            {% endif %}

                {% for const_name, const_val in attribute(meta.constatins, prop_name).extra %}
                        <td>{{ const_name }}: {{ const_val }}</td>
                {% endfor %}


            {%if attribute(meta.constatins, prop_name).extra %}
            </tr>
            {% endif %}
        </tr>
    {% endfor %}
</table>
{% endif %}

{% if schema.type == \'array\' %}
    <span class="label label-default">array</span>
    <table class="table">
        {% for key, item in schema.items %}
            <tr>
                <td>{{ key }}</td>
                {% if key == \'$ref\' %}
                    <td><a href="../schema/{{ schema.id  }}">{{ item }}</a></td>
                {% else %}
                    <td>{{ item | json_encode()}}</td>
                {% endif %}
            </tr>
        {% endfor %}
    </table>
{% endif %}
{% if schema.id is defined %}
    <div>
        <h6>
            <span class="badge badge-default">id</span>
            <span>{{ schema.id }}</span> <a href="../schema/{{ schema.id  }}"> <i class="fas fa-cloud-download-alt"></i></a>

        </h6>
    </div>
{% endif %}
{% if schema.type is defined %}
    <div>
        <h6>
            <span class="badge badge-default">type</span>
            <span>{{ schema.type }}</span>
        </h6>
    </div>
{% endif %}
{% if schema.title is defined %}
    <div>
        <h6>
            <span class="badge badge-default">title</span>
            <span>{{ schema.title }}</span>
        </h6>
    </div>
{% endif %}
{% if schema.required is defined %}
    <div>
        <h6>
            <span class="badge badge-default">required</span>
            <span>{{ schema.required | join(\', \')}}</span>
        </h6>
    </div>
{% endif %}
{% if schema.additionalProperties is defined %}
    <div>
        <h6>
            <span class="badge badge-default">additionalProperties</span>
            <span>{{ schema.additionalProperties ? \'true\' : \'false\'}}</span>
        </h6>
    </div>
{% endif %}
';
}
