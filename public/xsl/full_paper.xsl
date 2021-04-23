<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:exsl="http://exslt.org/common"
                xmlns:xs="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                version="1.0"
                extension-element-prefixes="exsl"
                xmlns="http://www.openarchives.org/OAI/2.0/">

    <xsl:import href="abandon_continue_publication_process_button.xsl"/>

    <xsl:output method="html" encoding="utf-8" indent="yes"/>

    <xsl:template match="record">

        <xsl:variable name="client_language" select="php:function('Episciences_Tools::getLocale')"/>
        <xsl:variable name="doc_language" select="metadata/oai_dc:dc/dc:language"/>
        <xsl:variable name="title">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $client_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $doc_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang]"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="description">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang = $client_language]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang = $doc_language]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description" disable-output-escaping="yes"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <!--xsl:variable name="pIdentifier" select="php:function('Ccsd_Tools::translate', 'Identifiant permanent')"/-->
        <!--xsl:variable name="dIdentifier" select="php:function('Ccsd_Tools::translate', 'Identifiant du document')"/-->

        <div class="panel panel-default collapsable" style="margin-top: 20px">

            <div class="panel-heading">
                <h2 class="panel-title" style="margin-bottom: 5px">

                    <span class="darkgrey">
                        <xsl:for-each select="metadata/oai_dc:dc/dc:creator[position() &lt;= 5]">
                            <xsl:value-of select="."/>
                            <xsl:if test="position() != last()"> and </xsl:if>
                        </xsl:for-each>
                        <xsl:if test="count(metadata/oai_dc:dc/dc:creator) &gt; 5">
                            <i> et al.</i>
                        </xsl:if>
                        -
                    </span>

                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>

                    <!--
                    <xsl:if test="episciences/version">
                        (v<xsl:value-of select="episciences/version" /><xsl:if test="episciences/tmp = 1"> - <xsl:value-of select="php:function('Ccsd_Tools::translate', 'version temporaire')" /></xsl:if>)
                    </xsl:if>
                     -->

                </h2>

                <xsl:if test="episciences/paperId and episciences/paperId != 0">
                    <!-- <xsl:value-of select="$pIdentifier"/> -> -->
                    <xsl:value-of select="episciences/review_code"/>:<xsl:value-of select="episciences/paperId"/> -
                </xsl:if>

                <!--
                <xsl:if test="episciences/id and episciences/id != 0 and episciences/id != episciences/paperId">
                    <xsl:value-of select="$dIdentifier"/> ->
                    <xsl:value-of select="episciences/review_code"/>:<xsl:value-of select="episciences/id"/> -
                </xsl:if>
                -->

                <xsl:value-of select="episciences/review"/>
                <xsl:if test="episciences/status = 16 and episciences/publication_date">,
                    <xsl:value-of
                            select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date))"/>
                </xsl:if>
                <xsl:if test="episciences/volume and episciences/volume != 0">,
                    <xsl:value-of
                            select="php:function('Ccsd_Tools::translate', concat('volume_',episciences/volume,'_title'))"/>
                </xsl:if>

                <xsl:if test="episciences/doi and episciences/doi != ''">
                    -
                    <a rel="noopener" target="_blank">
                        <xsl:attribute name="href">
                            <xsl:text>https://doi.org/</xsl:text><xsl:value-of select="episciences/doi"/>
                        </xsl:attribute>
                        https://doi.org/<xsl:value-of select="episciences/doi"/>
                    </a>
                </xsl:if>
            </div>

            <div class="panel-body in">

                <strong>
                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>
                </strong>

                <p>
                    <i>
                        <div>
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Auteurs : ')"/>
                            <xsl:for-each select="metadata/oai_dc:dc/dc:creator">
                                <xsl:value-of select="."/>
                                <xsl:if test="position() != last()"> and </xsl:if>
                            </xsl:for-each>
                        </div>

                    </i>
                </p>

                <p class="small" style="text-align: justify">
                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($description))"/>
                </p>

                <hr/>

                <!--
                <p><small><a target="_blank">
                <xsl:attribute name="href">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:identifier" />
                </xsl:attribute>
                <xsl:value-of select="metadata/oai_dc:dc/dc:identifier" />
                </a></small></p>
                -->
                <xsl:if test="episciences/doi and episciences/doi != ''">
                    <div class="paper-doi small">
                        <a rel="noopener" target="_blank">
                            <xsl:attribute name="href">
                                <xsl:text>https://doi.org/</xsl:text><xsl:value-of select="episciences/doi"/>
                            </xsl:attribute>
                            https://doi.org/<xsl:value-of select="episciences/doi"/>
                        </a>
                    </div>
                </xsl:if>


                <xsl:if test="episciences/identifier and episciences/identifier != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Source : ')"/>
                        <xsl:if test="episciences/docURL != episciences/paperURL">
                            <a target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="episciences/docURL"/>
                                </xsl:attribute>
                                <xsl:value-of select="episciences/identifier"/>
                            </a>
                        </xsl:if>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/volume and episciences/volume != 0">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Volume : ')"/>
                        <xsl:value-of
                                select="php:function('Ccsd_Tools::translate', concat('volume_',episciences/volume,'_title'))"/>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/section and episciences/section != 0">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Rubrique : ')"/>
                        <xsl:value-of
                                select="php:function('Ccsd_Tools::translate', concat('section_',episciences/section,'_title'))"/>
                    </div>
                </xsl:if>

                <xsl:choose>
                    <xsl:when test="episciences/status = 16 and episciences/publication_date">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Publié le : ')"/>
                            <xsl:value-of
                                    select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date))"/>
                        </div>
                    </xsl:when>
                    <!--
                      <xsl:when test="episciences/status = 5">
                          <div class="small">
                              <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Refusé le : ')" />
                              <xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(header/volume))" />
                          </div>
                    </xsl:when>
                    -->
                </xsl:choose>


                <xsl:if test="episciences/submission_date and episciences/submission_date != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Déposé le : ')"/>
                        <xsl:value-of
                                select="php:function('Episciences_View_Helper_Date::Date', string(episciences/submission_date))"/>
                    </div>
                </xsl:if>


                <div class="small">
                    <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Mots-clés : ')"/>
                    <xsl:for-each select="metadata/oai_dc:dc/dc:subject">
                        <xsl:value-of select="."/>
                        <xsl:if test="position() != last()">,</xsl:if>
                    </xsl:for-each>
                </div>

                <br/>


                <xsl:if test="(episciences/status = 0) and (episciences/uid = php:function('Episciences_Auth::getUid') and episciences/hasOtherVersions = 0)">
                    <a>
                        <xsl:attribute name="href">
                            <xsl:value-of select="concat('/paper/remove/id/', episciences/id)"/>
                        </xsl:attribute>
                        <button class="btn btn-danger btn-sm" style="margin-right: 5px">
                            <span class="glyphicon glyphicon-remove-circle" style="margin-right: 5px"/>
                            <xsl:variable name="string">
                                <xsl:text>Supprimer l'article</xsl:text>
                            </xsl:variable>
                            <xsl:value-of
                                    select="php:function('Ccsd_Tools::translate', $string)"/>
                        </button>
                    </a>
                </xsl:if>

                <xsl:if test="episciences">
                    <xsl:choose>
                        <xsl:when test="episciences/tmp/text() = '1'">
                            <xsl:variable name="docUrls"
                                          select="php:function('Episciences_Tools::buildHtmlTmpDocUrls', episciences/id)"/>
                            <xsl:value-of select=" $docUrls" disable-output-escaping="yes"/>
                        </xsl:when>
                        <xsl:otherwise>
                            <a target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:choose>
                                        <xsl:when test="episciences/status = 16">
                                            <xsl:value-of select="concat('/', episciences/id, '/pdf')"/>
                                        </xsl:when>
                                        <xsl:otherwise>
                                            <xsl:value-of select="episciences/paperURL"/>
                                        </xsl:otherwise>
                                    </xsl:choose>
                                </xsl:attribute>
                                <xsl:if test="episciences/notHasHook/text() = '1'">
                                    <button class="btn btn-default btn-sm" style="margin-right: 5px">
                                        <span class="fas fa-file-download" style="margin-right: 5px"/>
                                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Télécharger le fichier')"/>
                                    </button>
                                </xsl:if>
                            </a>

                            <xsl:if test="episciences/docURL != episciences/paperURL">
                                <a rel="noopener" target="_blank">
                                    <xsl:attribute name="href">
                                        <xsl:value-of select="episciences/docURL"/>
                                    </xsl:attribute>
                                    <button class="btn btn-default btn-sm">
                                        <span class="fas fa-external-link-alt" style="margin-right: 5px"/>
                                        <xsl:variable name="string">
                                            <xsl:text>Visiter la page de l'article</xsl:text>
                                        </xsl:variable>
                                        <xsl:value-of select="php:function('Ccsd_Tools::translate', $string)"/>
                                    </button>
                                </a>
                            </xsl:if>
                        </xsl:otherwise>
                    </xsl:choose>

                    <!-- Abondonner (reprendre) le processus de publication-->
                    <xsl:if test="episciences/uid = php:function('Episciences_Auth::getUid')">
                        <xsl:apply-imports/>
                    </xsl:if>
                </xsl:if>
            </div>

        </div>

    </xsl:template>

</xsl:stylesheet>
