<div class="tesfana-dashboard-wrap">
  <div class="tdf-header">
    <div>
      <h1>🐄 {{ 'Tesfana Dairy Dashboard'|t }}</h1>
      <p class="subtitle">{{ 'Operational overview'|t }}</p>
    </div>
    <div class="tdf-header-actions">
      {% if add_cow_url %}<a href="{{ add_cow_url }}" class="tdf-btn">{{ 'Add Cow'|t }}</a>{% endif %}
      {% if log_milk_url %}<a href="{{ log_milk_url }}" class="tdf-btn">{{ 'Add Milk'|t }}</a>{% endif %}
      {% if bcs_url %}<a href="{{ bcs_url }}" class="tdf-btn">{{ 'Record BCS'|t }}</a>{% endif %}
      {% if milk_tests_url %}<a href="{{ milk_tests_url }}" class="tdf-btn">{{ 'Milk Quality'|t }}</a>{% endif %}
      {% if milk_csv_url %}<a href="{{ milk_csv_url }}" class="tdf-btn tdf-btn-secondary">{{ 'Milk CSV'|t }}</a>{% endif %}
      {% if quickbooks_csv_url %}<a href="{{ quickbooks_csv_url }}" class="tdf-btn tdf-btn-secondary">{{ 'QuickBooks CSV'|t }}</a>{% endif %}
      {% if settings_url %}<a href="{{ settings_url }}" class="tdf-btn tdf-btn-secondary">{{ 'Settings'|t }}</a>{% endif %}
      <span data-form-queue-badge class="tdf-badge">0</span>
    </div>
  </div>

  <div class="tdf-kpis">
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Milk Today'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.milk_today ?? 0 }} <small>L</small></div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Milk Yesterday'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.milk_yesterday ?? 0 }} <small>L</small></div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Total Milk Logs'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.total_milk_logs ?? 0 }}</div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Revenue Today'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.revenue_today ?? 0 }} <small>{{ kpis.currency|default('') }}</small></div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Revenue (30 days)'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.revenue_30d ?? 0 }} <small>{{ kpis.currency|default('') }}</small></div>
    </div>

    {# New KPIs #}
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ '7-Day Total'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.milk_7d_total ?? 0 }} <small>L</small></div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ '7-Day Avg / Day'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.milk_7d_avg ?? 0 }} <small>L</small></div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'Cows Logged (7d)'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.cows_7d ?? 0 }}</div>
    </div>
    <div class="tdf-kpi">
      <div class="tdf-kpi-title">{{ 'MTD Revenue'|t }}</div>
      <div class="tdf-kpi-value">{{ kpis.revenue_mtd ?? 0 }} <small>{{ kpis.currency|default('') }}</small></div>
    </div>
  </div>

  <div class="tdf-grid">
    <div class="tdf-card">
      <div class="tdf-card-title">{{ 'Milk Output (last 30 days)'|t }}</div>
      <div id="milk-output-chart" class="chart-box"></div>
    </div>

    <div class="tdf-card">
      <div class="tdf-card-title">{{ 'Task Calendar'|t }}</div>
      <div id="task-calendar" class="calendar-box"></div>
    </div>
  </div>

  {% if notices %}
    <div class="tdf-card">
      <div class="tdf-card-title">{{ 'Notices'|t }}</div>
      <ul class="tdf-list">
        {% for n in notices %}
          <li>{{ n }}</li>
        {% endfor %}
      </ul>
    </div>
  {% endif %}
</div>
